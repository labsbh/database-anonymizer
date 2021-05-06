<?php

declare(strict_types=1);

namespace DatabaseAnonymizer\Tests\Unit\Generator;

use Faker\Factory;
use PHPUnit\Framework\TestCase;
use WebnetFr\DatabaseAnonymizer\Generator\FakerGenerator;

/**
 * @author Vlad Riabchenko <vriabchenko@webnet.fr>
 */
class FakerTest extends TestCase
{
    public function testFakerGenerators(): void
    {
        self::assertEquals(4, $this->generateValue(['formatter' => 'randomDigit', 'seed' => 'seed']));
        self::assertEquals(5, $this->generateValue(['formatter' => 'randomDigitNotNull', 'seed' => 'seed']));
        self::assertEquals(44886, $this->generateValue(['formatter' => 'randomNumber', 'arguments' => [5], 'seed' => 'seed']));
        self::assertEquals(8.977, $this->generateValue(['formatter' => 'randomFloat', 'arguments' => [3, 0, 20], 'seed' => 'seed']));
        self::assertEquals(9528, $this->generateValue(['formatter' => 'numberBetween', 'arguments' => [1000, 20000], 'seed' => 'seed']));
        self::assertEquals('l', $this->generateValue(['formatter' => 'randomLetter', 'arguments' => [0, 20000], 'seed' => 'seed']));
        self::assertEquals(['b'], $this->generateValue(['formatter' => 'randomElements', 'arguments' => [['a', 'b', 'c'], 1], 'seed' => 'seed']));
        self::assertEquals('b', $this->generateValue(['formatter' => 'randomElement', 'arguments' => [['a', 'b', 'c']], 'seed' => 'seed']));
        self::assertEquals('ducimus', $this->generateValue(['formatter' => 'word', 'seed' => 'seed']));
        self::assertEquals('Dr.', $this->generateValue(['formatter' => 'title', 'seed' => 'seed']));
        self::assertEquals('South', $this->generateValue(['formatter' => 'cityPrefix', 'seed' => 'seed']));
        self::assertEquals('+16575448831', $this->generateValue(['formatter' => 'e164PhoneNumber', 'seed' => 'seed']));
    }

    /**
     * @param array $config
     *
     * @return mixed
     */
    public function generateValue(array $config)
    {
        $formatter = $config['formatter'];

        $locale = $config['locale'] ?? 'en_US';
        $generator = Factory::create($locale);

        $seed = $config['seed'] ?? false;
        if ($seed) {
            $generator->seed($seed);
        }

        if ($config['unique'] ?? false) {
            $generator = $generator->unique();
        }

        $optional = $config['optional'] ?? false;
        if ($optional) {
            $generator = $generator->optional($optional);
        }

        $arguments = $config['arguments'] ?? [];

        $fakerGenerator = new FakerGenerator($generator, $formatter, $arguments, $config);

        return $fakerGenerator->generate();
    }
}
