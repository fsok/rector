<?php

declare(strict_types=1);

namespace Rector\DocumentationGenerator\OutputFormatter;

use Nette\Utils\Strings;
use Rector\ConsoleDiffer\MarkdownDifferAndFormatter;
use Rector\Core\Contract\Rector\RectorInterface;
use Rector\Core\Contract\RectorDefinition\CodeSampleInterface;
use Rector\Core\Exception\ShouldNotHappenException;
use Rector\Core\RectorDefinition\ComposerJsonAwareCodeSample;
use Rector\Core\RectorDefinition\ConfiguredCodeSample;
use Rector\Core\RectorDefinition\RectorDefinition;
use Rector\DocumentationGenerator\PhpKeywordHighlighter;
use Rector\DocumentationGenerator\RectorMetadataResolver;
use Rector\PHPUnit\TestClassResolver\TestClassResolver;
use Rector\SymfonyPhpConfig\Printer\ReturnClosurePrinter;
use ReflectionClass;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symplify\SmartFileSystem\SmartFileInfo;

final class MarkdownDumpRectorsOutputFormatter
{
    /**
     * @var SymfonyStyle
     */
    private $symfonyStyle;

    /**
     * @var MarkdownDifferAndFormatter
     */
    private $markdownDifferAndFormatter;

    /**
     * @var RectorMetadataResolver
     */
    private $rectorMetadataResolver;

    /**
     * @var TestClassResolver
     */
    private $testClassResolver;

    /**
     * @var PhpKeywordHighlighter
     */
    private $phpKeywordHighlighter;

    /**
     * @var ReturnClosurePrinter
     */
    private $returnClosurePrinter;

    public function __construct(
        MarkdownDifferAndFormatter $markdownDifferAndFormatter,
        PhpKeywordHighlighter $phpKeywordHighlighter,
        RectorMetadataResolver $rectorMetadataResolver,
        ReturnClosurePrinter $returnClosurePrinter,
        SymfonyStyle $symfonyStyle,
        TestClassResolver $testClassResolver
    ) {
        $this->symfonyStyle = $symfonyStyle;
        $this->markdownDifferAndFormatter = $markdownDifferAndFormatter;
        $this->rectorMetadataResolver = $rectorMetadataResolver;
        $this->testClassResolver = $testClassResolver;
        $this->phpKeywordHighlighter = $phpKeywordHighlighter;
        $this->returnClosurePrinter = $returnClosurePrinter;
    }

    /**
     * @param RectorInterface[] $packageRectors
     */
    public function format(array $packageRectors, bool $isRectorProject): void
    {
        $totalRectorCount = count($packageRectors);
        $message = sprintf('# All %d Rectors Overview', $totalRectorCount);

        $this->symfonyStyle->writeln($message);
        $this->symfonyStyle->newLine();

        if ($isRectorProject) {
            $this->symfonyStyle->writeln('- [Projects](#projects)');
            $this->printRectorsWithHeadline($packageRectors, 'Projects');
        } else {
            $this->printRectors($packageRectors, $isRectorProject);
        }
    }

    /**
     * @param RectorInterface[] $rectors
     * @return RectorInterface[][]
     */
    private function groupRectorsByPackage(array $rectors): array
    {
        $rectorsByPackage = [];
        foreach ($rectors as $rector) {
            $rectorClass = get_class($rector);
            $package = $this->rectorMetadataResolver->resolvePackageFromRectorClass($rectorClass);
            $rectorsByPackage[$package][] = $rector;
        }

        // sort groups by name to make them more readable
        ksort($rectorsByPackage);

        return $rectorsByPackage;
    }

    /**
     * @param RectorInterface[][] $rectorsByGroup
     */
    private function printGroupsMenu(array $rectorsByGroup): void
    {
        foreach ($rectorsByGroup as $group => $rectors) {
            $escapedGroup = str_replace('\\', '', $group);
            $escapedGroup = Strings::webalize($escapedGroup, '_');
            $message = sprintf('- [%s](#%s) (%d)', $group, $escapedGroup, count($rectors));

            $this->symfonyStyle->writeln($message);
        }

        $this->symfonyStyle->newLine();
    }

    private function printRector(RectorInterface $rector, bool $isRectorProject): void
    {
        $headline = $this->getRectorClassWithoutNamespace($rector);

        if ($isRectorProject) {
            $message = sprintf('### `%s`', $headline);
            $this->symfonyStyle->writeln($message);
        } else {
            $message = sprintf('## `%s`', $headline);
            $this->symfonyStyle->writeln($message);
        }

        $rectorClass = get_class($rector);

        $this->symfonyStyle->newLine();
        $message = sprintf(
            '- class: [`%s`](%s)',
            get_class($rector),
            $this->resolveClassFilePathOnGitHub($rectorClass)
        );
        $this->symfonyStyle->writeln($message);

        $rectorTestClass = $this->testClassResolver->resolveFromClassName($rectorClass);
        if ($rectorTestClass !== null) {
            $fixtureDirectoryPath = $this->resolveFixtureDirectoryPathOnGitHub($rectorTestClass);
            if ($fixtureDirectoryPath !== null) {
                $message = sprintf('- [test fixtures](%s)', $fixtureDirectoryPath);
                $this->symfonyStyle->writeln($message);
            }
        }

        $rectorDefinition = $rector->getDefinition();
        $this->ensureRectorDefinitionExists($rectorDefinition, $rector);

        $this->symfonyStyle->newLine();

        $description = $rectorDefinition->getDescription();
        $codeHighlightedDescription = $this->phpKeywordHighlighter->highlight($description);
        $this->symfonyStyle->writeln($codeHighlightedDescription);

        $this->ensureCodeSampleExists($rectorDefinition, $rector);

        foreach ($rectorDefinition->getCodeSamples() as $codeSample) {
            $this->symfonyStyle->newLine();

            $this->printConfiguration($rector, $codeSample);
            $this->printCodeSample($codeSample);
        }

        $this->symfonyStyle->newLine();
        $this->symfonyStyle->writeln('<br><br>');
        $this->symfonyStyle->newLine();
    }

    private function getRectorClassWithoutNamespace(RectorInterface $rector): string
    {
        $rectorClass = get_class($rector);
        $rectorClassParts = explode('\\', $rectorClass);

        return $rectorClassParts[count($rectorClassParts) - 1];
    }

    private function resolveClassFilePathOnGitHub(string $className): string
    {
        $classRelativePath = $this->getClassRelativePath($className);
        return '/../master/' . $classRelativePath;
    }

    private function resolveFixtureDirectoryPathOnGitHub(string $className): ?string
    {
        $classRelativePath = $this->getClassRelativePath($className);

        $fixtureDirectory = dirname($classRelativePath) . '/Fixture';
        if (is_dir($fixtureDirectory)) {
            return '/../master/' . $fixtureDirectory;
        }

        return null;
    }

    private function printConfiguration(RectorInterface $rector, CodeSampleInterface $codeSample): void
    {
        if (! $codeSample instanceof ConfiguredCodeSample) {
            return;
        }

        $configuration = [
            get_class($rector) => $codeSample->getConfiguration(),
        ];

        $phpConfigContent = $this->returnClosurePrinter->printServices($configuration);
        $this->printCodeWrapped($phpConfigContent, 'php');

        $this->symfonyStyle->newLine();
        $this->symfonyStyle->writeln('↓');
        $this->symfonyStyle->newLine();
    }

    private function printCodeSample(CodeSampleInterface $codeSample): void
    {
        $diff = $this->markdownDifferAndFormatter->bareDiffAndFormatWithoutColors(
            $codeSample->getCodeBefore(),
            $codeSample->getCodeAfter()
        );

        $this->printCodeWrapped($diff, 'diff');

        $extraFileContent = $codeSample->getExtraFileContent();
        if ($extraFileContent !== null) {
            $this->symfonyStyle->newLine();
            $this->symfonyStyle->writeln('**New file**');
            $this->symfonyStyle->newLine();
            $this->printCodeWrapped($extraFileContent, 'php');
        }

        if ($codeSample instanceof ComposerJsonAwareCodeSample) {
            $composerJsonContent = $codeSample->getComposerJsonContent();
            $this->symfonyStyle->newLine(1);
            $this->symfonyStyle->writeln('`composer.json`');
            $this->symfonyStyle->newLine(1);
            $this->printCodeWrapped($composerJsonContent, 'json');
            $this->symfonyStyle->newLine();
        }
    }

    private function printCodeWrapped(string $content, string $format): void
    {
        $message = sprintf('```%s%s%s%s```', $format, PHP_EOL, rtrim($content), PHP_EOL);
        $this->symfonyStyle->writeln($message);
    }

    private function getClassRelativePath(string $className): string
    {
        $rectorReflectionClass = new ReflectionClass($className);
        $rectorSmartFileInfo = new SmartFileInfo($rectorReflectionClass->getFileName());

        return $rectorSmartFileInfo->getRelativeFilePathFromCwd();
    }

    /**
     * @param RectorInterface[] $rectors
     */
    private function printRectorsWithHeadline(array $rectors, string $headline): void
    {
        if (count($rectors) === 0) {
            return;
        }

        $this->symfonyStyle->writeln('---');
        $this->symfonyStyle->newLine();

        $this->symfonyStyle->writeln('## ' . $headline);
        $this->symfonyStyle->newLine();

        $this->printRectors($rectors, true);
    }

    /**
     * @param RectorInterface[] $rectors
     */
    private function printRectors(array $rectors, bool $isRectorProject): void
    {
        $groupedRectors = $this->groupRectorsByPackage($rectors);

        if ($isRectorProject) {
            $this->printGroupsMenu($groupedRectors);
        }

        foreach ($groupedRectors as $group => $rectors) {
            if ($isRectorProject) {
                $this->symfonyStyle->writeln('## ' . $group);
                $this->symfonyStyle->newLine();
            }

            foreach ($rectors as $rector) {
                $this->printRector($rector, $isRectorProject);
            }
        }
    }

    private function ensureRectorDefinitionExists(RectorDefinition $rectorDefinition, RectorInterface $rector): void
    {
        if ($rectorDefinition->getDescription() !== '') {
            return;
        }

        $message = sprintf(
            'Rector "%s" is missing description. Complete it in "%s()" method.',
            get_class($rector),
            'getDefinition'
        );
        throw new ShouldNotHappenException($message);
    }

    private function ensureCodeSampleExists(RectorDefinition $rectorDefinition, RectorInterface $rector): void
    {
        if (count($rectorDefinition->getCodeSamples()) !== 0) {
            return;
        }

        throw new ShouldNotHappenException(sprintf(
            'Rector "%s" must have at least one code sample. Complete it in "%s()" method.',
            get_class($rector),
            'getDefinition'
        ));
    }
}
