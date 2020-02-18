<?php

namespace Rector\Php55\Tests\Rector\FuncCall\PregReplaceEModifierRector\Fixture;

class SkipNoEModifier
{
    public function run()
    {
        $comment = preg_replace('~(\w)(\w+)~', 'test', $comment);

        $comment = preg_replace(['~(\w)(\w+)~'], 'test', $comment);
    }
}