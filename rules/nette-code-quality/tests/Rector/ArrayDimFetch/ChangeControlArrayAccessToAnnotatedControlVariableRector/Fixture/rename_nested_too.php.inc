<?php

namespace Rector\NetteCodeQuality\Tests\Rector\ArrayDimFetch\ChangeControlArrayAccessToAnnotatedControlVariableRector\Fixture;

use Nette\Application\UI\Presenter;
use Nette\Application\UI\Form;

final class SomeOtherPresenter extends Presenter
{
    public function run()
    {
        if ($this['some_form']->isSubmitted()) {
            return $this['some_form']->getValues();
        }
    }

    protected function createComponentSomeForm()
    {
        return new Form();
    }
}

?>
-----
<?php

namespace Rector\NetteCodeQuality\Tests\Rector\ArrayDimFetch\ChangeControlArrayAccessToAnnotatedControlVariableRector\Fixture;

use Nette\Application\UI\Presenter;
use Nette\Application\UI\Form;

final class SomeOtherPresenter extends Presenter
{
    public function run()
    {
        /** @var \Nette\Application\UI\Form $someForm */
        $someForm = $this['some_form'];
        if ($someForm->isSubmitted()) {
            return $someForm->getValues();
        }
    }

    protected function createComponentSomeForm()
    {
        return new Form();
    }
}

?>
