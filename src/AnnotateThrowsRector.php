<?php

declare(strict_types=1);

namespace SavinMikhail\AnnotateThrowsRector;

use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\ContainerFactory;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ThrowsTagValueNode;
use PHPStan\Rules\Exceptions\DefaultExceptionTypeResolver;
use PHPStan\Type\Type;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\TryCatch;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\BetterPhpDocParser\ValueObject\Type\FullyQualifiedIdentifierTypeNode;
use Rector\Comments\NodeDocBlock\DocBlockUpdater;
use Rector\Configuration\Option;
use Rector\Configuration\Parameter\SimpleParameterProvider;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Rector\Reflection\MethodReflectionResolver;
use SavinMikhail\AnnotateThrowsRector\ValueObject\MethodAnalysis;
use SavinMikhail\AnnotateThrowsRector\ValueObject\MethodCallEdge;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use PHPStan\Reflection\ReflectionProvider;

use function array_diff;
use function array_key_exists;
use function array_map;
use function array_unique;
use function getcwd;
use function class_exists;
use function interface_exists;
use function is_a;
use function is_array;
use function is_dir;
use function is_string;
use function ltrim;
use function mkdir;
use function sort;
use function strcasecmp;
use function sprintf;
use function sys_get_temp_dir;

final class AnnotateThrowsRector extends AbstractRector implements ConfigurableRectorInterface
{
    public const INCLUDE_UNCHECKED = 'include_unchecked';
    public const EXCLUDED_EXCEPTION_CLASSES = 'excluded_exception_classes';

    /**
     * @var string[]
     */
    private const SCALAR_PHPDOC_TYPES = [
        'array',
        'bool',
        'callable',
        'false',
        'float',
        'int',
        'iterable',
        'mixed',
        'never',
        'null',
        'object',
        'parent',
        'self',
        'static',
        'string',
        'true',
        'void',
    ];

    /**
     * @var array<string, string[]>
     */
    private array $externalThrowsCache = [];

    private ?DefaultExceptionTypeResolver $exceptionTypeResolver = null;

    private bool $includeUnchecked = false;

    /**
     * @var string[]
     */
    private array $excludedExceptionClasses = [
        \Throwable::class,
        \Exception::class,
        \Error::class,
        \RuntimeException::class,
        \LogicException::class,
    ];

    public function __construct(
        private readonly PhpDocInfoFactory $phpDocInfoFactory,
        private readonly DocBlockUpdater $docBlockUpdater,
        private readonly ReflectionProvider $reflectionProvider,
        private readonly MethodReflectionResolver $methodReflectionResolver,
    ) {
    }

    public function configure(array $configuration): void
    {
        $this->includeUnchecked = (bool) ($configuration[self::INCLUDE_UNCHECKED] ?? false);

        if (array_key_exists(self::EXCLUDED_EXCEPTION_CLASSES, $configuration)) {
            $this->excludedExceptionClasses = $this->normalizeTypes($configuration[self::EXCLUDED_EXCEPTION_CLASSES]);
            return;
        }

        if ($this->includeUnchecked) {
            $this->excludedExceptionClasses = [];
        }
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add missing @throws tags for direct throws and same-class propagation',
            [
                new CodeSample(
                    <<<'PHP'
final class Example
{
    public function run(): void
    {
        $this->fail();
    }

    private function fail(): void
    {
        throw new \RuntimeException();
    }
}
PHP,
                    <<<'PHP'
final class Example
{
    /**
     * @throws \RuntimeException
     */
    public function run(): void
    {
        $this->fail();
    }

    /**
     * @throws \RuntimeException
     */
    private function fail(): void
    {
        throw new \RuntimeException();
    }
}
PHP,
                ),
            ],
        );
    }

    public function getNodeTypes(): array
    {
        return [ClassLike::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof ClassLike) {
            return null;
        }

        $methods = $this->collectMethods($node);
        if ($methods === []) {
            return null;
        }

        $currentClassName = $this->resolveCurrentClassName($node);
        $scope = $node->getAttribute(AttributeKey::SCOPE);
        if (!$scope instanceof Scope) {
            $scope = null;
        }
        $analyses = [];
        foreach ($methods as $methodName => $method) {
            $analyses[$methodName] = $this->analyzeMethod($method, $currentClassName);
        }

        $resolvedThrows = [];
        foreach ($analyses as $methodName => $analysis) {
            $resolvedThrows[$methodName] = $this->normalizeTypes([
                ...$analysis->existingThrows,
                ...$analysis->directThrows,
            ]);
        }

        $hasChanged = true;
        while ($hasChanged) {
            $hasChanged = false;

            foreach ($analyses as $methodName => $analysis) {
                $nextThrows = $resolvedThrows[$methodName];

                foreach ($analysis->methodCallEdges as $methodCallEdge) {
                    foreach ($this->resolveEdgeThrows($methodCallEdge, $currentClassName, $resolvedThrows) as $calleeThrowType) {
                        if ($this->isCaughtByAny($calleeThrowType, $methodCallEdge->caughtTypes)) {
                            continue;
                        }

                        $nextThrows[] = $calleeThrowType;
                    }
                }

                $nextThrows = $this->normalizeTypes($nextThrows);
                if ($nextThrows === $resolvedThrows[$methodName]) {
                    continue;
                }

                $resolvedThrows[$methodName] = $nextThrows;
                $hasChanged = true;
            }
        }

        $classChanged = false;
        foreach ($methods as $methodName => $method) {
            $missingThrows = array_diff($resolvedThrows[$methodName], $analyses[$methodName]->existingThrows);
            if ($missingThrows === []) {
                continue;
            }

            $phpDocInfo = $this->phpDocInfoFactory->createFromNodeOrEmpty($method);
            $methodChanged = false;
            foreach ($missingThrows as $missingThrow) {
                if (!$this->shouldAnnotateException($missingThrow, $scope)) {
                    continue;
                }

                $phpDocInfo->addPhpDocTagNode(new PhpDocTagNode(
                    '@throws',
                    new ThrowsTagValueNode(
                        new FullyQualifiedIdentifierTypeNode($missingThrow),
                        '',
                    ),
                ));
                $methodChanged = true;
            }

            if (!$methodChanged) {
                continue;
            }

            $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($method);
            $classChanged = true;
        }

        return $classChanged ? $node : null;
    }

    /**
     * @return array<string, ClassMethod>
     */
    private function collectMethods(ClassLike $classLike): array
    {
        $methods = [];
        foreach ($classLike->getMethods() as $method) {
            if ($method->stmts === null) {
                continue;
            }

            $methodName = $this->getName($method);
            $methods[$methodName] = $method;
        }

        return $methods;
    }

    private function analyzeMethod(ClassMethod $classMethod, ?string $currentClassName): MethodAnalysis
    {
        $existingThrows = $this->extractThrowsFromPhpDocInfo($this->phpDocInfoFactory->createFromNode($classMethod));
        $directThrows = [];
        $methodCallEdges = [];

        $this->collectThrowsAndCalls(
            nodes: $classMethod->stmts ?? [],
            directThrows: $directThrows,
            methodCallEdges: $methodCallEdges,
            caughtTypes: [],
            currentClassName: $currentClassName,
        );

        return new MethodAnalysis(
            existingThrows: $existingThrows,
            directThrows: $this->normalizeTypes($directThrows),
            methodCallEdges: $methodCallEdges,
        );
    }

    /**
     * @param Node[] $nodes
     * @param string[] $directThrows
     * @param MethodCallEdge[] $methodCallEdges
     * @param string[] $caughtTypes
     */
    private function collectThrowsAndCalls(
        array $nodes,
        array &$directThrows,
        array &$methodCallEdges,
        array $caughtTypes,
        ?string $currentClassName,
    ): void {
        foreach ($nodes as $node) {
            if ($node instanceof TryCatch) {
                $tryCaughtTypes = $caughtTypes;
                foreach ($node->catches as $catch) {
                    $tryCaughtTypes = [...$tryCaughtTypes, ...$this->resolveCatchTypes($catch)];
                }

                $this->collectThrowsAndCalls($node->stmts, $directThrows, $methodCallEdges, $tryCaughtTypes, $currentClassName);

                foreach ($node->catches as $catch) {
                    $this->collectThrowsAndCalls($catch->stmts, $directThrows, $methodCallEdges, $caughtTypes, $currentClassName);
                }

                $this->collectThrowsAndCalls($node->finally->stmts ?? [], $directThrows, $methodCallEdges, $caughtTypes, $currentClassName);
                continue;
            }

            if ($node instanceof ClassMethod) {
                continue;
            }

            if ($node instanceof Throw_) {
                foreach ($this->resolveThrownTypeNames($node->expr) as $throwType) {
                    if ($this->isCaughtByAny($throwType, $caughtTypes)) {
                        continue;
                    }

                    $directThrows[] = $throwType;
                }
            }

            foreach ($this->resolveCallEdges($node, $currentClassName, $caughtTypes) as $methodCallEdge) {
                $methodCallEdges[] = $methodCallEdge;
            }

            foreach ($node->getSubNodeNames() as $subNodeName) {
                $subNode = $node->{$subNodeName};
                if ($subNode instanceof Node) {
                    $this->collectThrowsAndCalls([$subNode], $directThrows, $methodCallEdges, $caughtTypes, $currentClassName);
                    continue;
                }

                if (!is_array($subNode)) {
                    continue;
                }

                $subNodes = [];
                foreach ($subNode as $item) {
                    if ($item instanceof Node) {
                        $subNodes[] = $item;
                    }
                }

                if ($subNodes !== []) {
                    $this->collectThrowsAndCalls($subNodes, $directThrows, $methodCallEdges, $caughtTypes, $currentClassName);
                }
            }
        }
    }

    /**
     * @return string[]
     */
    private function extractThrowsFromPhpDocInfo(?PhpDocInfo $phpDocInfo): array
    {
        if (!$phpDocInfo instanceof PhpDocInfo) {
            return [];
        }

        $throws = [];
        foreach ($phpDocInfo->getTagsByName('throws') as $tagNode) {
            if (!$tagNode->value instanceof ThrowsTagValueNode) {
                continue;
            }

            $throws = [...$throws, ...$this->extractTypeNamesFromString((string) $tagNode->value->type)];
        }

        return $this->normalizeTypes($throws);
    }

    /**
     * @return string[]
     */
    private function resolveThrownTypeNames(Node $expr): array
    {
        return $this->normalizeTypes($this->resolveTypeClassNames($this->getType($expr)));
    }

    /**
     * @return string[]
     */
    private function resolveTypeClassNames(?Type $type): array
    {
        if (!$type instanceof Type) {
            return [];
        }

        return $type->getObjectClassNames();
    }

    /**
     * @param string[] $caughtTypes
     * @return MethodCallEdge[]
     */
    private function resolveCallEdges(Node $node, ?string $currentClassName, array $caughtTypes): array
    {
        if ($node instanceof MethodCall) {
            $methodName = $this->getName($node->name);
            if (!is_string($methodName)) {
                return [];
            }

            $classNames = $this->isName($node->var, 'this')
                ? [$currentClassName]
                : $this->normalizeTypes($this->getType($node->var)->getObjectClassNames());

            return $this->createEdgesForClassNames($classNames, $methodName, $caughtTypes);
        }

        if (!$node instanceof StaticCall) {
            return [];
        }

        $methodName = $this->getName($node->name);
        if (!is_string($methodName)) {
            return [];
        }

        if ($node->class instanceof Name && $this->isNames($node->class, ['self', 'static'])) {
            return $this->createEdgesForClassNames([$currentClassName], $methodName, $caughtTypes);
        }

        if (!$node->class instanceof Name) {
            return [];
        }

        $className = $this->getName($node->class);

        return $this->createEdgesForClassNames([$className], $methodName, $caughtTypes);
    }

    /**
     * @param array<string, string[]> $resolvedThrows
     * @return string[]
     */
    private function resolveEdgeThrows(MethodCallEdge $methodCallEdge, ?string $currentClassName, array $resolvedThrows): array
    {
        if ($methodCallEdge->className === null || $methodCallEdge->className === $currentClassName) {
            return $resolvedThrows[$methodCallEdge->methodName] ?? [];
        }

        return $this->resolveExternalMethodThrows($methodCallEdge->className, $methodCallEdge->methodName);
    }

    /**
     * @param string[] $classNames
     * @param string[] $caughtTypes
     * @return MethodCallEdge[]
     */
    private function createEdgesForClassNames(array $classNames, string $methodName, array $caughtTypes): array
    {
        $normalizedCaughtTypes = $this->normalizeTypes($caughtTypes);

        return array_map(
            static fn (string $className): MethodCallEdge => new MethodCallEdge(
                className: $className,
                methodName: $methodName,
                caughtTypes: $normalizedCaughtTypes,
            ),
            array_values(array_filter($classNames, static fn (?string $className): bool => is_string($className) && $className !== '')),
        );
    }

    /**
     * @return string[]
     */
    private function resolveExternalMethodThrows(string $className, string $methodName): array
    {
        $cacheKey = sprintf('%s::%s', $className, $methodName);
        if (array_key_exists($cacheKey, $this->externalThrowsCache)) {
            return $this->externalThrowsCache[$cacheKey];
        }

        if (!$this->reflectionProvider->hasClass($className)) {
            return $this->externalThrowsCache[$cacheKey] = [];
        }

        $methodReflection = $this->methodReflectionResolver->resolveMethodReflection(
            className: $className,
            methodName: $methodName,
            scope: null,
        );

        if ($methodReflection === null) {
            return $this->externalThrowsCache[$cacheKey] = [];
        }

        $throws = $this->resolveTypeClassNames($methodReflection->getThrowType());

        $docComment = $methodReflection->getDocComment();
        if ($throws === [] && is_string($docComment) && $docComment !== '') {
            $throws = [
                ...$throws,
                ...$this->extractThrowsFromDocComment($docComment),
            ];
        }

        return $this->externalThrowsCache[$cacheKey] = $this->normalizeTypes($throws);
    }

    /**
     * @return string[]
     */
    private function extractThrowsFromDocComment(string $docComment): array
    {
        $virtualNode = new Nop([
            'comments' => [new Doc($docComment)],
        ]);

        return $this->extractThrowsFromPhpDocInfo($this->phpDocInfoFactory->createFromNode($virtualNode));
    }

    private function resolveCurrentClassName(ClassLike $classLike): ?string
    {
        $namespacedName = $classLike->getAttribute(AttributeKey::NAMESPACED_NAME);
        if ($namespacedName instanceof Name) {
            return $namespacedName->toString();
        }

        if ($classLike->name instanceof Node\Identifier) {
            return $classLike->name->toString();
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function resolveCatchTypes(Catch_ $catch): array
    {
        $caughtTypes = [];
        foreach ($catch->types as $type) {
            $caughtTypes[] = $type->toString();
        }

        return $this->normalizeTypes($caughtTypes);
    }

    /**
     * @param string[] $types
     * @return string[]
     */
    private function normalizeTypes(array $types): array
    {
        $normalizedTypes = [];
        foreach ($types as $type) {
            $normalizedType = ltrim($type, '\\');
            if ($normalizedType === '' || $this->isScalarPhpDocType($normalizedType)) {
                continue;
            }

            $normalizedTypes[] = $normalizedType;
        }

        $normalizedTypes = array_values(array_unique($normalizedTypes));
        sort($normalizedTypes);

        return $normalizedTypes;
    }

    /**
     * @return string[]
     */
    private function extractTypeNamesFromString(string $type): array
    {
        preg_match_all('~\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*~', $type, $matches);

        $types = [];
        foreach ($matches[0] as $match) {
            if ($this->isScalarPhpDocType($match)) {
                continue;
            }

            $types[] = $match;
        }

        return $this->normalizeTypes($types);
    }

    /**
     * @param string[] $caughtTypes
     */
    private function isCaughtByAny(string $throwType, array $caughtTypes): bool
    {
        foreach ($caughtTypes as $caughtType) {
            if ($this->isCaughtBy($throwType, $caughtType)) {
                return true;
            }
        }

        return false;
    }

    private function isCaughtBy(string $throwType, string $caughtType): bool
    {
        $normalizedThrowType = ltrim($throwType, '\\');
        $normalizedCaughtType = ltrim($caughtType, '\\');

        if (strcasecmp($normalizedCaughtType, 'Throwable') === 0) {
            return true;
        }

        if (strcasecmp($normalizedThrowType, $normalizedCaughtType) === 0) {
            return true;
        }

        if (!(class_exists($normalizedThrowType) || interface_exists($normalizedThrowType))) {
            return false;
        }

        if (!(class_exists($normalizedCaughtType) || interface_exists($normalizedCaughtType))) {
            return false;
        }

        return is_a($normalizedThrowType, $normalizedCaughtType, true);
    }

    private function isScalarPhpDocType(string $type): bool
    {
        foreach (self::SCALAR_PHPDOC_TYPES as $scalarPhpDocType) {
            if (strcasecmp($type, $scalarPhpDocType) === 0) {
                return true;
            }
        }

        return false;
    }

    private function shouldAnnotateException(string $exceptionClass, ?Scope $scope): bool
    {
        if ($this->isExcludedException($exceptionClass)) {
            return false;
        }

        if ($this->includeUnchecked || !$scope instanceof Scope) {
            return true;
        }

        return $this->getExceptionTypeResolver()->isCheckedException($exceptionClass, $scope);
    }

    private function isExcludedException(string $exceptionClass): bool
    {
        foreach ($this->excludedExceptionClasses as $excludedExceptionClass) {
            if (strcasecmp($exceptionClass, $excludedExceptionClass) === 0) {
                return true;
            }
        }

        return false;
    }

    private function getExceptionTypeResolver(): DefaultExceptionTypeResolver
    {
        if ($this->exceptionTypeResolver instanceof DefaultExceptionTypeResolver) {
            return $this->exceptionTypeResolver;
        }

        $phpstanConfigPaths = SimpleParameterProvider::hasParameter(Option::PHPSTAN_FOR_RECTOR_PATHS)
            ? SimpleParameterProvider::provideArrayParameter(Option::PHPSTAN_FOR_RECTOR_PATHS)
            : [];
        $tempDirectory = sys_get_temp_dir() . '/annotate-throws-rector-phpstan';
        if (!is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0777, true);
        }

        $containerFactory = new ContainerFactory(getcwd());
        $container = $containerFactory->create(
            tempDirectory: $tempDirectory,
            additionalConfigFiles: $phpstanConfigPaths,
            analysedPaths: [],
        );

        return $this->exceptionTypeResolver = $container->getByType(DefaultExceptionTypeResolver::class);
    }
}
