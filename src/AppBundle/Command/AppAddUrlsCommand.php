<?php

namespace AppBundle\Command;

use Abraham\TwitterOAuth\TwitterOAuth;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AppAddUrlsCommand extends ContainerAwareCommand {
	protected function configure(){
		$this
			->setName('app:add-urls')
			->setDescription('...')
			->addArgument('argument', InputArgument::OPTIONAL, 'Argument description')
			->addOption('option', null, InputOption::VALUE_NONE, 'Option description');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return void
	 */
	protected function execute(InputInterface $input, OutputInterface $output){
		$twitter_token = $this->getStoredTwitterToken();

		if (empty($twitter_token)){
			$output->writeln('No Twitter token has been created so cannot access Twitter');
		}

		$pocket_token = $this->pocketTokenFromFile();

		if (empty($pocket_token)){
			$output->writeln('No Pocket token has been created so cannot access Pocket');
		}

		$output->writeln('Got Twitter and Pocket tokens, retrieving tweets');

		$connection = $this->getTwitter($twitter_token['oauth_token'], $twitter_token['oauth_token_secret']);
		$statuses = $connection->get('statuses/home_timeline', ['exclude_replies' => true, 'count' => 100]);

		if (empty($statuses)){
			$output->writeln('Not able to get Tweets');
		}

		$count = count($statuses);
		$output->writeln("Got $count Tweets");

		$urls = $this->getUrlsFromTweets($statuses);

		if (empty($urls)){
			$output->writeln('No valid URLs in Tweets');
		}

		$count = count($urls);
		$output->writeln("Got $count URLs");

		$output->write($urls, true);
	}

	/**
	 * @return array|string
	 */
	private function getStoredTwitterToken(){
		$file_path = $this->getTwitterFile();
		$token_store = file_exists($file_path) ? file_get_contents($file_path) : '';
		$token_store = json_decode($token_store, true) ?: [];

		return $token_store;
	}

	/**
	 * @return string
	 */
	private function getTwitterFile(){
		$file_path = $this->getContainer()->get('kernel')->getRootDir();
		$file_name = $file_path.'/token_twitter';

		return $file_name;
	}

	/**
	 * @param string $token
	 * @param string $secret
	 * @return TwitterOAuth
	 */
	private function getTwitter($token = null, $secret = null){
		$connection = new TwitterOAuth($this->getParameter('twitter_key'), $this->getParameter('twitter_secret'), $token, $secret);

		return $connection;
	}

	/**
	 * @param string $name
	 * @return mixed
	 */
	private function getParameter($name){
		return $this->getContainer()->getParameter($name);
	}

	/**
	 * @param array $statuses
	 * @return array
	 */
	private function getUrlsFromTweets(array $statuses){
		$parsed_urls = [];

		foreach ($statuses as &$tweet){
			$urls = $tweet->entities->urls;

			if (empty($urls)){
				continue;
			}

			foreach ($urls as $url){
				if ($this->containsIgnoredUrl($url->expanded_url)){
					continue;
				}

				$parsed_url = $this->expandShortUrl($url->expanded_url);

				if ($this->containsIgnoredUrl($parsed_url)){
					continue;
				}

				$parsed_urls[] = $parsed_url;
			}
		}

		return $parsed_urls;
	}

	/**
	 * @param string $url
	 * @return bool
	 */
	private function containsIgnoredUrl($url){
		$ignored = [
			'twitter.com',
			't.co',
			'youtu.be',
			'www.youtube.com',
			'www.facebook.com',
			'facebook.com',
			'www.swarmapp.com',
		];

		$extra_ignored = $this->getParameter('extra_ignore_urls');
		$ignored = array_merge($extra_ignored, $ignored);

		$url = str_replace(['http://', 'https://'], '', $url);
		foreach ($ignored as $ignore){
			if (stripos($url, $ignore)===0){
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $url
	 * @return string
	 */
	private function expandShortUrl(string $url) : string{
		$headers = get_headers($url, 1);

		if (!empty($headers['Location'])){
			return is_array($headers['Location']) ? end($headers['Location']) : $headers['Location'];
		}

		return $url;
	}

	/**
	 * @return string
	 */
	private function pocketTokenFromFile(){
		$file_name = $this->getPocketFile();

		return file_get_contents($file_name);
	}

	/**
	 * @return string
	 */
	private function getPocketFile(){
		$file_path = $this->getContainer()->get('kernel')->getRootDir();
		$file_name = $file_path.'/access_token';

		return $file_name;
	}
}
