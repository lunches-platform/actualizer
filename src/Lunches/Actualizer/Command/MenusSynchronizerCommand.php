<?php

namespace Lunches\Actualizer\Command;

use Knp\Command\Command;
use Lunches\Actualizer\Synchronizer\MenusSynchronizer;
use Monolog\Logger;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
        $this->setName('synchronizer:menus')
            ->addArgument('instance', InputArgument::REQUIRED, 'API instance (company) to synchronize menus')
            ->addOption('menuType', null, InputOption::VALUE_OPTIONAL, 'One of Diet or Regular menu to synchronizer')
            ->addOption('weekRange', null, InputOption::VALUE_OPTIONAL, 'Week range with two dates: startDate and endDate in a format which menu from google sheet has')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var array $menusSheets */
        $menusSheets = $this->getSilexApplication()['google-sheets:menus'];
        $menusSynchronizer = $this->getMenusSynchronizer($input, $output);

        $menuType = $input->getOption('menuType');
        $weekRange = $input->getOption('weekRange');
        $filters = array_filter([
            'menuType' => $menuType,
            'weekRange' => $weekRange,
        ]);

        try {
            foreach ($menusSheets as $sheet) {
                $stat = $menusSynchronizer->sync($sheet['id'], $sheet['range'], $filters);
                $output->writeln($stat);
            }
        } catch (\Exception $e) {
            $this->getConsoleLogger($output)->addError($e->getMessage());
        }

        return 0;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return MenusSynchronizer
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     * @throws \InvalidArgumentException
     */
    private function getMenusSynchronizer(InputInterface $input, OutputInterface $output)
    {
        $instance = $input->getArgument('instance');
        $instances = array_map(function ($instance) {
            return $instance['key'];
        }, $this->getSilexApplication()['instances']);

        if (!in_array($instance, $instances, true)) {
            throw new \InvalidArgumentException('Provided instance not found. Please try again');
        }

        /** @var MenusSynchronizer $synchronizer */
        $synchronizer = $this->getSilexApplication()["synchronizer:menus:{$instance}"];
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
