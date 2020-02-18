<?php

namespace Rector\Php55\Tests\Rector\FuncCall\PregReplaceEModifierRector\Fixture;

class PatternArray
{
    public function run()
    {
        $comment = preg_replace(['~\b(\w)(\w+)~e', '~(\w)(\w+)~e'], '"$1".strtolower("$2")', $comment);
    }
}

?>
-----
<?php

namespace Rector\Php55\Tests\Rector\FuncCall\PregReplaceEModifierRector\Fixture;

class PatternArray
{
    public function run()
    {
        $comment = preg_replace_callback(['~\b(\w)(\w+)~', '~(\w)(\w+)~'], function ($matches) {
            return $matches[1] . strtolower($matches[2]);
        }, $comment);
    }
}

?>
