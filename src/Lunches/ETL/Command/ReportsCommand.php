<?php

namespace Lunches\ETL\Command;

use Knp\Command\Command;
use Lunches\ETL\CookingPackingReport;
use Monolog\Logger;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReportsCommand extends Command
{
    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName('reports');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $data = $this->getCookReport($output)->forWeek();
//        $output->write($data);

        file_put_contents($this->getFilename(), $data);

        return 0;
    }

    /**
     * @param OutputInterface $output
     * @return CookingPackingReport
     */
    private function getCookReport(OutputInterface $output)
    {
        /** @var CookingPackingReport $report */
        $report = $this->getSilexApplication()['cooking-packing-report'];
        $report->setLogger($this->getConsoleLogger($output));

        return $report;
    }

    private function getFilename()
    {
        $rootDir =  $this->getSilexApplication()['root_dir'];

        return $rootDir.'/web/recorded_clients_table.html';
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
