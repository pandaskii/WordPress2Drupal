<?php

/**
 * @file Optimiz exported Wordpress XML.
 */

namespace Wordpress2Drupal\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wordpress2Drupal\Document\Document;

/**
 * Class OptimizCommand.
 * @package Wordpress2Drupal\Command
 */
class OptimizCommand extends Command
{
    /**
     * Command configure.
     */
    protected function configure()
    {
        $this
            ->setName('wordpress2drupal:optimize')
            ->setDescription('Optimize the exported Wordpress XML file')
            ->setHelp('This command helps you to optimize the exported Wordpress XML file');

        $this
            ->setDefinition(
                new InputDefinition(
                    array(
                        new InputOption(
                            'file',
                            'f',
                            InputOption::VALUE_REQUIRED,
                            'File path of the exported Wordpress XML file'
                        ),
                        new InputOption(
                            'save',
                            's',
                            InputOption::VALUE_OPTIONAL,
                            'Save the Wordpress exported XML file in a custom path for further usage',
                            '1'
                        ),
                    )
                )
            );
    }

    /**
     * Execute the command.
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Optimize the exported Wordpress XML');

        // Section - fetch XML information.
        $io->section('Fetch XML file information');
        $file_path = $input->getOption('file');

        if (!file_exists($file_path)) {
            throw new \InvalidArgumentException('File does NOT exist!');
        }

        // Start the document.
        $document = new Document($file_path);

        // Build up file object.
        $file = new \stdClass();
        $file->filename = basename($file_path);
        $file->uri = $file_path;
        $file->filemime = mime_content_type($file_path);
        $file->filesize = @filesize($file_path);

        $table = new Table($output);
        $table
            ->setHeaders(array('File name', 'Mime type', 'File size', 'File path'))
            ->setRows(
                array(
                    array(
                        $file->filename,
                        $file->filemime,
                        number_format($file->filesize / 1048576, 2).' MB',
                        $file->uri,
                    ),
                )
            );
        $table->render();
        $io->newLine();

        // Section - parse XML.
        $this->parse($document, $io);

        // Save the optimized file or display.
        $save = $input->getOption('save');
        if ($save == 1) {
            $this->save($document, $io);
        } else {
            $qp = $document->load();
            $qp->writeXML();
        }

        // Display the error or success message.
        $errors = $document->getErrors();
        if ($errors) {
            $io->error($errors);
        } else {
            $io->success('All done! cheers');
        }

        $io->newLine();
    }

    /**
     * Pare the Document.
     * @param Document $document
     * @param $io
     */
    protected function parse(Document $document, $io)
    {
        $io->section('Clean up XML file');
        $qp = $document->load();
        try {
            $items = $qp->find('item');
            $sizeOfItems = $items->count();
            $io->text('Found '.$sizeOfItems.' item(s)');
            if ($sizeOfItems > 0) {
                $io->progressStart($sizeOfItems);
                foreach ($items as $item) {
                    $io->note('Processing item: '.$item->find('title')->text());
                    $wp_postmeta = $item->xpath('wp:postmeta');
                    foreach ($wp_postmeta as $meta) {
                        $wp_meta_key = $meta->xpath('wp:meta_key[contains(text(),\'_fss_relevance\')]');
                        // Remove the meta.
                        if (!empty($wp_meta_key)) {
                            $meta->remove();
                        }
                    }
                    // Process one step.
                    $io->progressAdvance(1);
                }
                $io->progressFinish();
            }
        } catch (\Exception $exception) {
            $document->addError('Cannot parse XML file content');
        }
        $io->text('Done');
    }

    /**
     * Save the file.
     */
    protected function save(Document $document, $io)
    {
        // Section - parse XML.
        $io->section('Save the new XML file');
        $document->saveFile();
        $io->text('Done');
    }
}
