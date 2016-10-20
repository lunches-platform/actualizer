<?php

namespace Lunches\Actualizer\Command;

use Knp\Command\Command;
use Lunches\Actualizer\Synchronizer\MenusSynchronizer;
use Monolog\Logger;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MenusSynchronizerCommand.
 */
class MenusSynchronizerCommand extends Command
{
    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName('synchronizer:menus');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var array $menusSheets */
        $menusSheets = $this->getSilexApplication()['google-sheets:menus'];
        try {
            foreach ($menusSheets as $sheet) {
                $stat = $this->getMenusSynchronizer($output)->sync($sheet['id'], $sheet['range']);
                $output->writeln($stat);
            }
        } catch (\Exception $e) {
            $this->getConsoleLogger($output)->addError($e->getMessage());
        }

        return 0;
    }

    /**
     * @param OutputInterface $output
     * @return MenusSynchronizer
     */
    private function getMenusSynchronizer(OutputInterface $output)
    {
        /** @var MenusSynchronizer $synchronizer */
        $synchronizer = $this->getSilexApplication()['synchronizer:menus'];
        $synchronizer->setLogger($this->getConsoleLogger($output));
        
        return $synchronizer;
    }

    /**
     * @param OutputInterface $output
     * @return Logger
     */
    private function getConsoleLogger(OutputInterface $output)
    {
        /** @var Logger $logger */
        $logger = $this->getSilexApplication()['logger'];
        $consoleHandler =  new ConsoleHandler($output);
        $logger->pushHandler($consoleHandler);

        return $logger;
    }
}
