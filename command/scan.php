<?php

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Leizh <leizh@free.fr>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Thomas Müller 2013
 * @copyright Bart Visscher 2013
 * @copyright Leizh 2014
 * @copyright Pauli Järvinen 2017
 */

namespace OCA\Music\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


use OCA\Music\Utility\Scanner;

class Scan extends Command {
	/**
	 * @var \OCP\IUserManager $userManager
	 */
	private $userManager;
	/**
	 * @var  Scanner
	 */
	private $scanner;

	public function __construct(\OCP\IUserManager $userManager, $scanner) {
		$this->userManager = $userManager;
		$this->scanner = $scanner;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('music:scan')
			->setDescription('scan and index any unindexed audio files')
			->addArgument(
					'user_id',
					InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
					'scan new music files of the given user(s)'
			)
			->addOption(
					'all',
					null,
					InputOption::VALUE_NONE,
					'scan new music files of all known users'
			)
			->addOption(
					'debug',
					null,
					InputOption::VALUE_NONE,
					'will run the scan in debug mode (memory usage)'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		if (!$input->getOption('debug')) {
			$this->scanner->listen('\OCA\Music\Utility\Scanner', 'update', function($path) use ($output) {
				$output->writeln("Scanning <info>$path</info>");
			});
		}

		if ($input->getOption('all')) {
			$users = $this->userManager->search('');
		} else {
			$users = $input->getArgument('user_id');

			if (count($users) === 0) {
				$output->writeln("Specify either the target user(s) or --all");
			}
		}

		foreach ($users as $user) {
			if (is_object($user)) {
				$user = $user->getUID();
			}
			\OC_Util::tearDownFS();
			\OC_Util::setupFS($user);
			$output->writeln("Start scan for <info>$user</info>");
			$userHome = $this->scanner->resolveUserFolder($user);
			$unscanned = $this->scanner->getUnscannedMusicFileIds($user, $userHome);
			$output->writeln('Found ' . count($unscanned) . ' new music files');
			
			if (count($unscanned)) {
				$processedCount = $this->scanner->scanFiles(
						$user, $userHome, $unscanned,
						$input->getOption('debug') ? $output : null);
				$output->writeln("Added $processedCount files to database");
			}

			if ($this->scanner->findCovers()) {
				$output->writeln("Some cover image(s) were found and added");
			}
		}
	}
}
