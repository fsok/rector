<?php

namespace Rector\Symfony\Tests\Rector\Controller\AddFlashRector\Fixture;

use Symfony\Component\HttpFoundation\Request;

class AnyClass2
{
    public function someAction(Request $request)
    {
        $request->getSession()->getFlashBag()->add('success', 'message');
    }
}
