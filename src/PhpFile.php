<?php

declare(strict_types=1);

namespace Castor\Sylius;

use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

use function Castor\fs;

final class PhpFile
{
    /** @var array<Node\Stmt> */
    private array $ast;

    public function __construct(
        private string $path,
    ) {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $code = file_get_contents($this->path);

        if (false === $code) {
            throw new \RuntimeException(\sprintf('Unable to read "%s".', $this->path));
        }

        $ast = $parser->parse($code);

        if (null === $ast) {
            throw new \RuntimeException(\sprintf('Unable to parse "%s".', $this->path));
        }

        $this->ast = $ast;
    }

    public function save(): void
    {
        fs()->dumpFile(
            $this->path,
            (new Standard())->prettyPrintFile($this->ast),
        );
    }

    public function addImport(string $fqcn, ?string $alias = null): static
    {
        $namespace = $this->findNamespace();

        $target = null !== $namespace ? $namespace->stmts : $this->ast;

        if ($this->hasImport($target, $fqcn)) {
            return $this;
        }

        $insertPos = 0;

        foreach ($target as $i => $stmt) {
            if ($stmt instanceof Use_) {
                $insertPos = $i + 1;
            }

            if ($stmt instanceof Class_ || $stmt instanceof Node\Stmt\Enum_) {
                break;
            }
        }

        $useStmt = new Use_([
            new Node\UseItem(new Node\Name($fqcn), $alias),
        ]);

        array_splice($target, $insertPos, 0, [$useStmt]);

        if (null !== $namespace) {
            $namespace->stmts = $target;
        } else {
            $this->ast = $target;
        }

        return $this;
    }

    public function removeImport(string $fqcn): static
    {
        $namespace = $this->findNamespace();

        if (null !== $namespace) {
            foreach ($namespace->stmts as $i => $stmt) {
                if ($stmt instanceof Use_) {
                    foreach ($stmt->uses as $use) {
                        if ($use->name->toString() === $fqcn) {
                            unset($namespace->stmts[$i]);
                            $namespace->stmts = array_values($namespace->stmts);

                            return $this;
                        }
                    }
                }
            }

            return $this;
        }

        foreach ($this->ast as $i => $stmt) {
            if ($stmt instanceof Use_) {
                foreach ($stmt->uses as $use) {
                    if ($use->name->toString() === $fqcn) {
                        unset($this->ast[$i]);
                        $this->ast = array_values($this->ast);

                        return $this;
                    }
                }
            }
        }

        return $this;
    }

    public function addInterface(string $fqcn): static
    {
        $interfaceName = basename(str_replace('\\', '/', $fqcn));

        $class = $this->findClass();

        if (null === $class) {
            return $this;
        }

        foreach ($class->implements as $impl) {
            if ($impl->toString() === $interfaceName) {
                return $this;
            }
        }

        $class->implements[] = new Node\Name($interfaceName);

        return $this->addImport($fqcn);
    }

    public function removeInterface(string $fqcn): static
    {
        $interfaceName = basename(str_replace('\\', '/', $fqcn));

        $class = $this->findClass();

        if (null === $class) {
            return $this;
        }

        foreach ($class->implements as $i => $impl) {
            if ($impl->toString() === $interfaceName) {
                unset($class->implements[$i]);
                $class->implements = array_values($class->implements);

                return $this->removeImport($fqcn);
            }
        }

        return $this;
    }

    public function addTrait(string $fqcn): static
    {
        $traitName = basename(str_replace('\\', '/', $fqcn));

        $class = $this->findClass();

        if (null === $class) {
            return $this;
        }

        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\TraitUse) {
                foreach ($stmt->traits as $trait) {
                    if ($trait->toString() === $traitName) {
                        return $this;
                    }
                }
            }
        }

        array_unshift($class->stmts, new Node\Stmt\TraitUse([new Node\Name($traitName)]));

        return $this->addImport($fqcn);
    }

    public function removeTrait(string $fqcn): static
    {
        $traitName = basename(str_replace('\\', '/', $fqcn));

        $class = $this->findClass();

        if (null === $class) {
            return $this;
        }

        foreach ($class->stmts as $i => $stmt) {
            if ($stmt instanceof Node\Stmt\TraitUse) {
                foreach ($stmt->traits as $j => $trait) {
                    if ($trait->toString() === $traitName) {
                        unset($stmt->traits[$j]);
                        $stmt->traits = array_values($stmt->traits);

                        if ([] === $stmt->traits) {
                            unset($class->stmts[$i]);
                            $class->stmts = array_values($class->stmts);
                        }

                        return $this->removeImport($fqcn);
                    }
                }
            }
        }

        return $this;
    }

    public function removeConstructor(): static
    {
        $class = $this->findClass();

        if (null === $class) {
            return $this;
        }

        foreach ($class->stmts as $i => $stmt) {
            if ($stmt instanceof ClassMethod && '__construct' === $stmt->name->toString()) {
                unset($class->stmts[$i]);
                $class->stmts = array_values($class->stmts);

                return $this;
            }
        }

        return $this;
    }

    public function addConstructor(string $body): static
    {
        $class = $this->findClass();

        if (null === $class) {
            return $this;
        }

        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof ClassMethod && '__construct' === $stmt->name->toString()) {
                return $this;
            }
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $bodyAst = $parser->parse('<?php class __TEMP__ {' . "\n" . $body . "\n" . '}');

        if (null === $bodyAst) {
            return $this;
        }

        if ($bodyAst[0] instanceof Class_) {
            $class->stmts[] = $bodyAst[0]->stmts[0];
        }

        return $this;
    }

    public function addClassConstant(string $code): static
    {
        $class = $this->findClass();

        if (null === $class) {
            return $this;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $bodyAst = $parser->parse('<?php class __TEMP__ {' . "\n" . $code . "\n" . '}');

        if (null === $bodyAst || !$bodyAst[0] instanceof Class_) {
            return $this;
        }

        foreach ($bodyAst[0]->stmts as $classConst) {
            if (!$classConst instanceof Node\Stmt\ClassConst) {
                continue;
            }

            $alreadyAdded = false;

            foreach ($classConst->consts as $const) {
                if ($this->hasClassConstant($class, $const->name->toString())) {
                    $alreadyAdded = true;

                    break;
                }
            }

            if ($alreadyAdded) {
                continue;
            }

            $class->stmts[] = $classConst;
        }

        return $this;
    }

    public function addProperty(string $code): static
    {
        $class = $this->findClass();

        if (null === $class) {
            return $this;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $bodyAst = $parser->parse('<?php class __TEMP__ {' . "\n" . $code . "\n" . '}');

        if (null === $bodyAst || !$bodyAst[0] instanceof Class_) {
            return $this;
        }

        foreach ($bodyAst[0]->stmts as $property) {
            if (!$property instanceof Node\Stmt\Property) {
                continue;
            }

            $name = $property->props[0]->name->toString();

            if ($this->hasProperty($class, $name)) {
                continue;
            }

            $class->stmts[] = $property;
        }

        return $this;
    }

    public function addAttribute(string $code): static
    {
        $class = $this->findClass();

        if (null === $class) {
            return $this;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $bodyAst = $parser->parse('<?php ' . $code . ' class __TEMP__ {}');

        if (null === $bodyAst || !$bodyAst[0] instanceof Class_) {
            return $this;
        }

        foreach ($bodyAst[0]->attrGroups as $attrGroup) {
            $alreadyAdded = false;

            foreach ($attrGroup->attrs as $attr) {
                if ($this->hasAttribute($class, $attr->name->toString())) {
                    $alreadyAdded = true;

                    break;
                }
            }

            if (!$alreadyAdded) {
                $class->attrGroups[] = $attrGroup;
            }
        }

        return $this;
    }

    public function addMethod(string $code): static
    {
        $class = $this->findClass();

        if (null === $class) {
            return $this;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $bodyAst = $parser->parse('<?php class __TEMP__ {' . "\n" . $code . "\n" . '}');

        if (null === $bodyAst || !$bodyAst[0] instanceof Class_) {
            return $this;
        }

        foreach ($bodyAst[0]->stmts as $method) {
            if (!$method instanceof ClassMethod) {
                continue;
            }

            if ($this->hasMethod($class, $method->name->toString())) {
                continue;
            }

            $class->stmts[] = $method;
        }

        return $this;
    }

    public function appendToMethod(
        string $method,
        string $body,
    ): self {
        $target = $this->findMethod($method);

        $stmts = $this->parseStatements($body);

        $target->stmts = [
            ...($target->stmts ?? []),
            ...$stmts,
        ];

        return $this;
    }

    public function replaceMethodBody(
        string $method,
        string $body,
    ): self {
        $target = $this->findFunctionLike($method);

        if ($target instanceof Node\Stmt\Function_ || $target instanceof ClassMethod) {
            $target->stmts = $this->parseStatements($body);
        }

        return $this;
    }

    public function replaceFunctionBody(string $function, string $body): self
    {
        $target = $this->findFunctionLike($function);

        if ($target instanceof Node\Stmt\Function_ || $target instanceof ClassMethod) {
            $target->stmts = $this->parseStatements($body);
        }

        return $this;
    }

    public function findClassConstant(string $name): mixed
    {
        foreach ($this->ast as $node) {
            if (!$node instanceof Node\Stmt\Namespace_) {
                continue;
            }

            foreach ($node->stmts as $stmt) {
                if (!$stmt instanceof Class_) {
                    continue;
                }

                foreach ($stmt->stmts as $classStmt) {
                    if (!$classStmt instanceof Node\Stmt\ClassConst) {
                        continue;
                    }

                    foreach ($classStmt->consts as $const) {
                        if ($const->name->toString() !== $name) {
                            continue;
                        }

                        return $this->normalizeNodeValue($const->value);
                    }
                }
            }
        }

        return null;
    }

    public function findFunction(string $name): Node\Stmt\Function_
    {
        $finder = new NodeFinder();

        $function = $finder->findFirst(
            $this->ast,
            static fn(Node $node): bool => $node instanceof Node\Stmt\Function_
                && $node->name->toString() === $name,
        );

        if (!$function instanceof Node\Stmt\Function_) {
            throw new \RuntimeException(\sprintf('Method "%s" not found.', $name));
        }

        return $function;
    }

    public function findMethod(string $name): ClassMethod
    {
        $finder = new NodeFinder();

        $method = $finder->findFirst(
            $this->ast,
            static fn(Node $node): bool => $node instanceof ClassMethod
                && $node->name->toString() === $name,
        );

        if (!$method instanceof ClassMethod) {
            throw new \RuntimeException(\sprintf('Method "%s" not found.', $name));
        }

        return $method;
    }

    private function normalizeNodeValue(Node\Expr $node): mixed
    {
        return match (true) {
            $node instanceof Node\Scalar\String_ => $node->value,

            $node instanceof Node\Scalar\Int_ => $node->value,

            $node instanceof Node\Scalar\Float_ => $node->value,

            $node instanceof Node\Expr\ConstFetch => match ($node->name->toLowerString()) {
                'true' => true,
                'false' => false,
                'null' => null,
                default => null,
            },

            $node instanceof Node\Expr\Array_ => $this->normalizeArrayValue($node),

            default => null,
        };
    }

    private function normalizeArrayValue(Node\Expr\Array_ $node): array
    {
        $result = [];

        foreach ($node->items as $item) {
            $key = $item->key;

            if (null === $key) {
                $result[] = $this->normalizeNodeValue($item->value);

                continue;
            }

            $result[$this->normalizeNodeValue($key)] = $this->normalizeNodeValue($item->value);
        }

        return $result;
    }

    /** @return array<Node\Stmt> */
    private function parseStatements(string $code): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        $stmts = $parser->parse('<?php ' . $code);

        if (null === $stmts) {
            throw new \RuntimeException('Unable to parse code.');
        }

        return $stmts;
    }

    private function findClass(): ?Class_
    {
        $visitor = new class extends NodeVisitorAbstract {
            public ?Class_ $classNode = null;

            public function enterNode(Node $node): mixed
            {
                if ($node instanceof Class_) {
                    $this->classNode = $node;
                }

                return null;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($this->ast);

        return $visitor->classNode;
    }

    private function findNamespace(): ?Node\Stmt\Namespace_
    {
        foreach ($this->ast as $stmt) {
            if ($stmt instanceof Node\Stmt\Namespace_) {
                return $stmt;
            }
        }

        return null;
    }

    private function findFunctionLike(string $name): FunctionLike
    {
        $finder = new NodeFinder();

        $node = $finder->findFirst(
            $this->ast,
            static fn(Node $node): bool => (
                $node instanceof Node\Stmt\Function_
                || $node instanceof ClassMethod
            ) && $name === $node->name->toString(),
        );

        if (!$node instanceof FunctionLike) {
            throw new \RuntimeException(\sprintf('Function "%s" not found.', $name));
        }

        return $node;
    }

    /** @param array<Node\Stmt> $stmts */
    private function hasImport(array $stmts, string $fqcn): bool
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Use_) {
                foreach ($stmt->uses as $use) {
                    if ($use->name->toString() === $fqcn) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function hasMethod(Class_ $class, string $name): bool
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof ClassMethod && $stmt->name->toString() === $name) {
                return true;
            }
        }

        return false;
    }

    private function hasClassConstant(Class_ $class, string $name): bool
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\ClassConst) {
                foreach ($stmt->consts as $const) {
                    if ($const->name->toString() === $name) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function hasProperty(Class_ $class, string $name): bool
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Property) {
                foreach ($stmt->props as $prop) {
                    if ($prop->name->toString() === $name) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function hasAttribute(Class_ $class, string $name): bool
    {
        foreach ($class->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($attr->name->toString() === $name) {
                    return true;
                }
            }
        }

        return false;
    }
}
