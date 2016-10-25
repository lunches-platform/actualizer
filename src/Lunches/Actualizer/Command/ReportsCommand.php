<?php

namespace Lunches\Actualizer\Command;

use Knp\Command\Command;
use Lunches\Actualizer\CookingPackingReport;
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
        $data = $this->getCookReport()->forWeek();
//        $output->write($data);

        file_put_contents($this->getFilename(), $data);

        return 0;
    }

    /**
     * @return CookingPackingReport
     */
    private function getCookReport()
    {
        return $this->getSilexApplication()['cook-report'];
    }

    private function getFilename()
    {
        $rootDir =  $this->getSilexApplication()['root_dir'];

        return $rootDir.'/recorded_clients_table.html';
    }
}
