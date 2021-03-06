<?php

declare(strict_types=1);

namespace Rector\Symfony\Rector\Form;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\RectorDefinition\CodeSample;
use Rector\Core\RectorDefinition\RectorDefinition;
use Rector\Symfony\FormHelper\FormTypeStringToTypeProvider;

/**
 * @see \Rector\Symfony\Tests\Rector\Form\FormTypeGetParentRector\FormTypeGetParentRectorTest
 */
final class FormTypeGetParentRector extends AbstractRector
{
    /**
     * @var FormTypeStringToTypeProvider
     */
    private $formTypeStringToTypeProvider;

    public function __construct(FormTypeStringToTypeProvider $formTypeStringToTypeProvider)
    {
        $this->formTypeStringToTypeProvider = $formTypeStringToTypeProvider;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Turns string Form Type references to their CONSTANT alternatives in `getParent()` and `getExtendedType()` methods in Form in Symfony',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Symfony\Component\Form\AbstractType;

class SomeType extends AbstractType
{
    public function getParent()
    {
        return 'collection';
    }
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
use Symfony\Component\Form\AbstractType;

class SomeType extends AbstractType
{
    public function getParent()
    {
        return \Symfony\Component\Form\Extension\Core\Type\CollectionType::class;
    }
}
CODE_SAMPLE
                ),
                new CodeSample(
                    <<<'CODE_SAMPLE'
use Symfony\Component\Form\AbstractTypeExtension;

class SomeExtension extends AbstractTypeExtension
{
    public function getExtendedType()
    {
        return 'collection';
    }
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
use Symfony\Component\Form\AbstractTypeExtension;

class SomeExtension extends AbstractTypeExtension
{
    public function getExtendedType()
    {
        return \Symfony\Component\Form\Extension\Core\Type\CollectionType::class;
    }
}
CODE_SAMPLE
                ),
            ]
        );
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $this->isClassAndMethodMatch($node)) {
            return null;
        }

        $this->traverseNodesWithCallable((array) $node->stmts, function (Node $node): ?Node {
            if (! $node instanceof Return_) {
                return null;
            }

            if ($node->expr === null) {
                return null;
            }

            if (! $node->expr instanceof String_) {
                return null;
            }

            $this->replaceStringWIthFormTypeClassConstIfFound($node->expr->value, $node);

            return $node;
        });

        return null;
    }

    private function isClassAndMethodMatch(ClassMethod $classMethod): bool
    {
        if ($this->isInObjectType($classMethod, 'Symfony\Component\Form\AbstractType')) {
            return $this->isName($classMethod->name, 'getParent');
        }

        if ($this->isInObjectType($classMethod, 'Symfony\Component\Form\AbstractTypeExtension')) {
            return $this->isName($classMethod->name, 'getExtendedType');
        }

        return false;
    }

    private function replaceStringWIthFormTypeClassConstIfFound(string $stringValue, Return_ $return): void
    {
        $formClass = $this->formTypeStringToTypeProvider->matchClassForNameWithPrefix($stringValue);
        if ($formClass === null) {
            return;
        }

        $return->expr = $this->createClassConstantReference($formClass);
    }
}
