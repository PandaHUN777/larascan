<?php

declare(strict_types=1);

namespace Larascan\Engine;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;

/**
 * AST Visitor to track occurrences of native Laravel 13 Facades, Utilities, and Helpers.
 */
final class InventoryVisitor extends NodeVisitorAbstract
{
    /** @var array<string, string> Short name => FQCN */
    private array $trackedClasses;

    /** @var array<string, string> FQCN => Short name */
    private array $fqcnToName;

    /** @var array<string, string> Lowercase function name => original function name */
    private array $lowercasedHelpers = [];

    /** @var array<string, int> Symbol => count */
    private array $usages = [];

    /** @var array<string, array<string>> Symbol => list of file paths */
    private array $fileLocations = [];

    private string $currentFile = '';

    private string $currentNamespace = '';

    /**
     * @param  array<string, string>  $trackedClasses
     * @param  array<string, string>  $trackedHelpers
     */
    public function __construct(array $trackedClasses, array $trackedHelpers)
    {
        $this->trackedClasses = $trackedClasses;
        $this->fqcnToName = array_flip($trackedClasses);

        foreach ($trackedHelpers as $helper) {
            $this->lowercasedHelpers[strtolower($helper)] = $helper;
        }
    }

    public function setCurrentFile(string $filePath): void
    {
        $this->currentFile = $filePath;
        $this->currentNamespace = '';
    }

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->currentNamespace = $node->name !== null ? $node->name->toString() : '';
            return null;
        }

        // 1. Static call, class constant fetch, or class instantiation
        if ($node instanceof StaticCall || $node instanceof ClassConstFetch || $node instanceof New_) {
            $this->checkClassNode($node->class);
            return null;
        }

        // 4. Function call: once(), defer(), retry(), now(), config()
        if ($node instanceof FuncCall && $node->name instanceof Name && count($node->name->getParts()) === 1) {
            $fnName = strtolower($node->name->getLast());
            if (isset($this->lowercasedHelpers[$fnName])) {
                $this->recordUsage($this->lowercasedHelpers[$fnName]);
            }
            return null;
        }

        return null;
    }

    private function checkClassNode(Node $classNode): void
    {
        if (! ($classNode instanceof Name)) {
            return;
        }

        $resolved = ltrim($classNode->toString(), '\\');
        $origNode = $classNode->getAttribute('originalName');
        $orig = $origNode instanceof Name ? $origNode->toString() : $resolved;

        // 1. Direct FQCN match: Illuminate\Support\Facades\Route
        if (isset($this->fqcnToName[$resolved])) {
            $this->recordUsage($this->fqcnToName[$resolved]);
            return;
        }

        // 2. Root alias or unimported global alias (e.g. Str::random() or Route::get())
        if (isset($this->trackedClasses[$orig])) {
            if ($resolved === $orig) {
                $this->recordUsage($orig);
                return;
            }

            if ($this->currentNamespace !== '' && $resolved === $this->currentNamespace . '\\' . $orig) {
                if ($this->isLocalSiblingClass($orig)) {
                    return;
                }

                $this->recordUsage($orig);
            }
        }
    }

    private function isLocalSiblingClass(string $className): bool
    {
        if ($this->currentFile === '') {
            return false;
        }

        return file_exists(dirname($this->currentFile) . '/' . $className . '.php');
    }

    private function recordUsage(string $symbol): void
    {
        $this->usages[$symbol] = ($this->usages[$symbol] ?? 0) + 1;

        if ($this->currentFile !== '') {
            $this->fileLocations[$symbol][$this->currentFile] = true;
        }
    }

    /**
     * @return array<string, int>
     */
    public function getUsages(): array
    {
        return $this->usages;
    }

    /**
     * @return array<string, array<string>>
     */
    public function getFileLocations(): array
    {
        $result = [];
        foreach ($this->fileLocations as $symbol => $files) {
            $result[$symbol] = array_keys($files);
        }

        return $result;
    }
}
