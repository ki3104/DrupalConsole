<?php
/**
 * @file
 * Contains \Drupal\Console\Command\Config\ImportCommand.
 */

namespace Drupal\Console\Command\Config;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Finder\Finder;
use Drupal\Console\Command\ContainerAwareCommand;
use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Console\Style\DrupalStyle;

class ImportCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('config:import')
            ->setDescription($this->trans('commands.config.import.description'))
            ->addOption(
                'file',
                null,
                InputOption::VALUE_OPTIONAL,
                $this->trans('commands.config.import.option.file')
            )
            ->addOption(
                'remove-files',
                false,
                InputOption::VALUE_NONE,
                $this->trans('commands.config.import.option.keep-files')
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new DrupalStyle($input, $output);

        $configFile = $input->getOption('file');
        $removeFiles = $input->getOption('remove-files');
        $configSyncDir = config_get_config_directory(
            CONFIG_SYNC_DIRECTORY
        );

        if ($configFile) {
            $archiveTar = new ArchiveTar($configFile, 'gz');

            $io->simple(
                $this->trans(
                    'commands.config.import.messages.config_files_imported'
                )
            );

            foreach ($archiveTar->listContent() as $file) {
                $io->info(
                    '[-] ' . $file['filename']
                );
            }

            try {
                $archiveTar->extract($configSyncDir . '/');
            } catch (\Exception $e) {
                $io->error($e->getMessage());

                return;
            }
        }

        $finder = new Finder();
        $finder->in($configSyncDir);
        $finder->name("*.yml");

        foreach ($finder as $configFile) {
            $configName = $configFile->getBasename('.yml');
            $configFilePath = sprintf(
                '%s/%s',
                $configSyncDir,
                $configFile->getBasename()
            );
            $config = $this->getConfigFactory()->getEditable($configName);
            $parser = new Parser();
            $configData = $parser->parse(
                file_get_contents($configFilePath)
            );

            $config->setData($configData);

            if ($removeFiles) {
                file_unmanaged_delete($configFilePath);
            }

            try {
                $config->save();
            } catch (\Exception $e) {
                $io->error($e->getMessage());

                return;
            }
        }

        $io->simple(sprintf($this->trans('commands.config.import.messages.imported'), CONFIG_SYNC_DIRECTORY));
    }
}
