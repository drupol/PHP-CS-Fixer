<?php

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer\Tests\Tokenizer\Analyzer;

use PhpCsFixer\Tests\TestCase;
use PhpCsFixer\Tokenizer\Analyzer\ClassAnalyzer;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * @author Pol Dellaiera <pol.dellaiera@protonmail.com>
 *
 * @internal
 *
 * @covers \PhpCsFixer\Tokenizer\Analyzer\ClassAnalyzer
 */
final class ClassAnalyzerTest extends TestCase
{
    /**
     * @param string $code
     * @param bool   $hasExtends
     * @param mixed  $classIndex
     *
     * @dataProvider provideClassHasExtendsCases
     */
    public function testClassHasExtends($classIndex, $code, $hasExtends)
    {
        $tokens = Tokens::fromCode($code);
        $analyzer = new ClassAnalyzer();

        $extends = null !== $analyzer->getClassExtends($tokens, $classIndex);

        static::assertSame($hasExtends, $extends);
    }

    public function provideClassHasExtendsCases()
    {
        return [
            [
                9,
                '<?php

namespace Foo\Bar;

class Nakano extends Izumi {

}',
                true,
            ],
            [
                9,
                '<?php

namespace Foo\Bar;

class Izumi {

}',
                false,
            ],
        ];
    }

    /**
     * @param string $code
     * @param int    $classIndex
     * @param array  $expected
     *
     * @dataProvider provideClassDefinitionInfoCases
     */
    public function testClassDefinitionInfo($code, $classIndex, $expected)
    {
        $tokens = Tokens::fromCode($code);
        $analyzer = new ClassAnalyzer();
        static::assertSame(serialize($expected), serialize($analyzer->getClassDefinition($tokens, $classIndex)));
    }

    public function provideClassDefinitionInfoCases()
    {
        return [
            [
                '<?php

namespace Foo\Bar;

class Nakano extends Izumi {

}',
                9,
                [
                    'start' => 9,
                    'classy' => 9,
                    'open' => 17,
                    'extends' => [
                        'start' => 13,
                        'numberOfExtends' => 1,
                        'multiLine' => false,
                    ],
                    'implements' => false,
                    'anonymousClass' => false,
                ],
            ],
            [
                '<?php

namespace Foo\Bar;

class Nakano extends Izumi implements CatInterface {

}',
                9,
                [
                    'start' => 9,
                    'classy' => 9,
                    'open' => 21,
                    'extends' => [
                        'start' => 13,
                        'numberOfExtends' => 1,
                        'multiLine' => false,
                    ],
                    'implements' => [
                        'start' => 17,
                        'numberOfImplements' => 1,
                        'multiLine' => false,
                    ],
                    'anonymousClass' => false,
                ],
            ],
            [
                '<?php

namespace Foo\Bar;

new class {};',
                11,
                [
                    'start' => 11,
                    'classy' => 11,
                    'open' => 13,
                    'extends' => false,
                    'implements' => false,
                    'anonymousClass' => true,
                ],
            ],
        ];
    }

    /**
     * @param string $source   PHP source code
     * @param string $label
     * @param array  $expected
     *
     * @dataProvider provideClassyImplementsInfoCases
     */
    public function testClassyInheritanceInfo($source, $label, array $expected)
    {
        $this->doTestClassyInheritanceInfo($source, $label, $expected);
    }

    /**
     * @param string $source   PHP source code
     * @param string $label
     * @param array  $expected
     *
     * @requires PHP 7.0
     * @dataProvider provideClassyInheritanceInfo7Cases
     */
    public function testClassyInheritanceInfo7($source, $label, array $expected)
    {
        $this->doTestClassyInheritanceInfo($source, $label, $expected);
    }

    public function provideClassyImplementsInfoCases()
    {
        return [
            [
                '<?php
class X11 implements    Z   , T,R
{
}',
                'numberOfImplements',
                ['start' => 5, 'numberOfImplements' => 3, 'multiLine' => false],
            ],
            [
                '<?php
class X10 implements    Z   , T,R    //
{
}',
                'numberOfImplements',
                ['start' => 5, 'numberOfImplements' => 3, 'multiLine' => false],
            ],
            [
                '<?php class A implements B {}',
                'numberOfImplements',
                ['start' => 5, 'numberOfImplements' => 1, 'multiLine' => false],
            ],
            [
                "<?php class A implements B,\n C{}",
                'numberOfImplements',
                ['start' => 5, 'numberOfImplements' => 2, 'multiLine' => true],
            ],
            [
                "<?php class A implements Z\\C\\B,C,D  {\n\n\n}",
                'numberOfImplements',
                ['start' => 5, 'numberOfImplements' => 3, 'multiLine' => false],
            ],
            [
                '<?php
namespace A {
    interface C {}
}

namespace {
    class B{}

    class A extends //
        B     implements /*  */ \A
        \C, Z{
        public function test()
        {
            echo 1;
        }
    }

    $a = new A();
    $a->test();
}',
                'numberOfImplements',
                ['start' => 36, 'numberOfImplements' => 2, 'multiLine' => true],
            ],
        ];
    }

    public function provideClassyInheritanceInfo7Cases()
    {
        return [
            [
                "<?php \$a = new    class(3)     extends\nSomeClass\timplements    SomeInterface, D {};",
                'numberOfExtends',
                ['start' => 12, 'numberOfExtends' => 1, 'multiLine' => true],
            ],
            [
                "<?php \$a = new class(4) extends\nSomeClass\timplements SomeInterface, D\n\n{};",
                'numberOfImplements',
                ['start' => 16, 'numberOfImplements' => 2, 'multiLine' => false],
            ],
            [
                "<?php \$a = new class(5) extends SomeClass\nimplements    SomeInterface, D {};",
                'numberOfExtends',
                ['start' => 12, 'numberOfExtends' => 1, 'multiLine' => true],
            ],
        ];
    }

    private function doTestClassyInheritanceInfo($source, $label, array $expected)
    {
        Tokens::clearCache();
        $tokens = Tokens::fromCode($source);
        static::assertTrue($tokens[$expected['start']]->isGivenKind([T_IMPLEMENTS, T_EXTENDS]), sprintf('Token must be "implements" or "extends", got "%s".', $tokens[$expected['start']]->getContent()));

        $analyzer = new ClassAnalyzer();
        $result = $analyzer->getClassInheritanceInfo($tokens, $expected['start'], $label);

        static::assertSame($expected, $result);
    }
}
