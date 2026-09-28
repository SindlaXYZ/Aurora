<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Command\CommandMiddlewareV1;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class CommandMiddlewareV1Test extends TestCase
{
    public function testTheCommandIsNamedNull(): void
    {
        $this->assertSame('null', new CommandMiddlewareV1()->getName());
    }

    public function testTheSettersStoreTheConsoleObjects(): void
    {
        $command        = $this->createCommand();
        $input          = new ArrayInput([]);
        $bufferedOutput = new BufferedOutput();
        $io             = new SymfonyStyle($input, new BufferedOutput());

        $command->call('setInput', $input);
        $command->call('setBufferOutput', $bufferedOutput);
        $command->call('setIo', $io);

        $this->assertSame(['input' => $input, 'bufferedOutput' => $bufferedOutput, 'io' => $io], $command->state());
    }

    public function testOutputWritesTheMessageWithOrWithoutANewLine(): void
    {
        $command = $this->createCommand();
        $command->call('setOutput', $output = new BufferedOutput());

        $command->call('output', 'first', false);
        $command->call('output', ' second');
        $command->call('output', 'third');

        $this->assertSame("first second\nthird\n", $output->fetch());
    }

    public function testOutputWithTimePrefixesTheTimeAndStripsTheTagsAndTheLineBreaks(): void
    {
        $command = $this->createCommand();
        $command->call('setOutput', $output = new BufferedOutput());

        $command->call('outputWithTime', "<info>Import</info> done\n");
        $command->call('outputWithTime', 'Next', true);

        $this->assertMatchesRegularExpression('/^\[\d{2}:\d{2}:\d{2}\] Import done\n\n\[\d{2}:\d{2}:\d{2}\] Next\n$/', $output->fetch());
    }

    #[DataProvider('dataGetDocCommentVar')]
    public function testGetDocCommentVar(string $docComment, ?string $expected): void
    {
        $this->assertSame($expected, $this->createCommand()->call('getDocCommentVar', $docComment));
    }

    public static function dataGetDocCommentVar(): array
    {
        return [
            'scalar'                   => ["/**\n * @var string\n */", 'string'],
            'nullable class'           => ["/**\n * @var ?\\DateTime\n */", 'DateTime'],
            'class or null'            => ["/**\n * @var \\DateTimeInterface|null\n */", 'DateTimeInterface'],
            'nullable scalar'          => ["/**\n * @var ?int\n */", 'int'],
            'array'                    => ["/**\n * @var int[]\n */", 'int[]'],
            'case-insensitive tag'     => ["/**\n * @VAR bool\n */", 'bool'],
            'no @var tag'              => ["/**\n * @ORM\\Column(type=\"string\")\n */", null],
        ];
    }

    #[DataProvider('dataGetDocDocumentORMType')]
    public function testGetDocDocumentORMType(string $docComment, ?string $expected): void
    {
        $this->assertSame($expected, $this->createCommand()->call('getDocDocumentORMType', $docComment));
    }

    public static function dataGetDocDocumentORMType(): array
    {
        return [
            'column'              => ['/** @ORM\Column(name="created_at", type="datetime", nullable=true) */', 'datetime'],
            'identifier'          => ['/** @ORM\Id @ORM\Column(type="integer") */', 'integer'],
            'relation'            => ['/** @ORM\ManyToOne(targetEntity="App\Entity\User") */', null],
            'no ORM annotation'   => ['/** @var string */', null],
        ];
    }

    #[DataProvider('dataGetDocDocumentORMTargetEntity')]
    public function testGetDocDocumentORMTargetEntity(string $docComment, bool $fullyQualifiedClassName, ?string $expected): void
    {
        $this->assertSame($expected, $this->createCommand()->call('getDocDocumentORMTargetEntity', $docComment, $fullyQualifiedClassName));
    }

    public static function dataGetDocDocumentORMTargetEntity(): array
    {
        $relation = '/** @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="posts") */';

        return [
            'fully qualified class name'         => [$relation, true, 'App\Entity\User'],
            'short class name'                   => [$relation, false, 'User'],
            'target without a namespace'         => ['/** @ORM\OneToMany(targetEntity="Post", mappedBy="user") */', false, 'Post'],
            'no target, fully qualified'         => ['/** @ORM\Column(type="string") */', true, null],
            'no target, short'                   => ['/** @ORM\Column(type="string") */', false, null],
        ];
    }

    #[DataProvider('dataCanBeNull')]
    public function testCanBeNull(string $docComment, bool $expected): void
    {
        $this->assertSame($expected, new CommandMiddlewareV1()->canBeNull($docComment));
    }

    public static function dataCanBeNull(): array
    {
        return [
            'nullable'                   => ['/** @ORM\Column(name="sent_at", type="datetime", nullable=true) */', true],
            'not nullable'               => ['/** @ORM\Column(name="sent_at", type="datetime", nullable=false) */', false],
            'not nullable join column'   => ['/** @ORM\JoinColumn(name="user_id", nullable=false) */', false],
            'nullable by default'        => ['/** @ORM\Column(type="string") */', true],
            'no ORM annotation'          => ['/** @var string */', true],
        ];
    }

    /**
     * A command that exposes the protected helpers of the middleware
     */
    private function createCommand(): CommandMiddlewareV1
    {
        return new class extends CommandMiddlewareV1 {
            public function call(string $method, mixed ...$arguments): mixed
            {
                return $this->$method(...$arguments);
            }

            /**
             * @return array<string, object>
             */
            public function state(): array
            {
                return ['input' => $this->input, 'bufferedOutput' => $this->bufferedOutput, 'io' => $this->io];
            }
        };
    }
}
