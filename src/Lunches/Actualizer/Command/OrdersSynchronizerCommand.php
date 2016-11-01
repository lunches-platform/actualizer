<?php

namespace Lunches\Actualizer\Command;

use Knp\Command\Command;
use Lunches\Actualizer\Synchronizer\OrdersSynchronizer;
use Monolog\Logger;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class OrdersSynchronizerCommand.
 */
class OrdersSynchronizerCommand extends Command
{
    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName('synchronizer:orders')->addArgument('instance', InputArgument::REQUIRED, 'API instance (company) to synchronize orders');
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
        /** @var array $ordersSheets */
        $ordersSheets = $this->getSilexApplication()['google-sheets:orders'];
        $ordersSynchronizer = $this->getOrdersSynchronizer($input, $output);
        try {
            foreach ($ordersSheets as $sheet) {
                $ordersSynchronizer->sync($sheet['id'], $sheet['range']);
            }
        } catch (\Exception $e) {
            $this->getConsoleLogger($output)->addError($e->getMessage());
        }

        return 0;
    }

    /**
     * @param OutputInterface $output
     * @param InputInterface $input
     * @return OrdersSynchronizer
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     * @throws \InvalidArgumentException
     */
    private function getOrdersSynchronizer(InputInterface $input, OutputInterface $output)
    {
        $instance = $input->getArgument('instance');
        $instances = array_map(function ($instance) {
            return $instance['key'];
        }, $this->getSilexApplication()['instances']);

        if (!in_array($instance, $instances, true)) {
            throw new \InvalidArgumentException('Provided instance not found. Please try again');
        }
        /** @var OrdersSynchronizer $synchronizer */
        $synchronizer = $this->getSilexApplication()["synchronizer:orders:{$instance}"];
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
