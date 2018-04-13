<?php
namespace Movim\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Respect\Validation\Validator;

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

use Movim\Daemon\Core;
use Movim\Daemon\Api;
use App\Configuration;

use React\EventLoop\Factory;
use React\Socket\Server as Reactor;

class DaemonCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('start')
            ->setDescription('Start the daemon')
            ->addOption(
                'url',
                null,
                InputOption::VALUE_REQUIRED,
                'Public URL of your Movim instance'
            )
            ->addOption(
                'port',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Port on which the daemon will listen',
                8080
            )
            ->addOption(
                'interface',
                'i',
                InputOption::VALUE_OPTIONAL,
                'Interface on which the daemon will listen',
                '127.0.0.1'
            )
            ->addOption(
                'debug',
                'd',
                InputOption::VALUE_NONE,
                'Output XMPP logs'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $loop = Factory::create();

        if (!Validator::url()->notEmpty()->validate($input->getOption('url'))) {
            $output->writeln('<error>Invalid or missing url parameter</error>');
            exit;
        }

        $baseuri = rtrim($input->getOption('url'), '/') . '/';

        $output->writeln('<info>Movim daemon launched</info>');
        $output->writeln('<info>Base URL: '.$baseuri.'</info>');

        $core = new Core($loop, $baseuri, $input);
        $app  = new HttpServer(new WsServer($core));

        $configuration = Configuration::findOrNew(1);

        if (empty($configuration->username) || empty($configuration->password)) {
            $output->writeln('<comment>Please set a username and password for the admin panel (' . $baseuri . '?admin)</comment>');

            $output->writeln('<info>To set those credentials run</info>');
            $output->writeln('<info>php mud.php config --username=USERNAME --password=PASSWORD</info>');
        }

        $socket = new Reactor(
            $input->getOption('interface').':'.$input->getOption('port'),
            $loop
        );

        $socketApi = new Reactor(1560, $loop);
        new Api($socketApi, $core);

        (new IoServer($app, $socket, $loop))->run();
    }
}
