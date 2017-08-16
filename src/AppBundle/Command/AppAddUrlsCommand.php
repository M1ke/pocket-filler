<?php

namespace AppBundle\Command;

use Abraham\TwitterOAuth\TwitterOAuth;
use Pocket;
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
			return;
		}

		$pocket_token = $this->pocketTokenFromFile();

		if (empty($pocket_token)){
			$output->writeln('No Pocket token has been created so cannot access Pocket');
			return;
		}

		$output->writeln('Got Twitter and Pocket tokens, retrieving tweets');

		$statuses = $this->fetchTweets($twitter_token, $output);

		if (empty($statuses)){
			$output->writeln('No new tweets found (check the recent id file), ending');
			return;
		}

		$count = count($statuses);
		$output->writeln("Got $count Tweets");

		$urls = $this->getUrlsFromTweets($statuses);

		if (empty($urls)){
			$output->writeln('No valid URLs in Tweets, ending');
			return;
		}

		$count = count($urls);
		$output->writeln("Got $count URLs");

		$output->write($urls, true);

		$this->addToPocket($urls, $pocket_token, $output);

		$this->storeRecentTweet($statuses);
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
				if ($this->notAUsefulArticle($url->expanded_url)){
					continue;
				}

				$parsed_url = $this->expandShortUrl($url->expanded_url);

				if ($this->notAUsefulArticle($parsed_url)){
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
	private function notAUsefulArticle($url){
		$url = $this->removeUrlProtocol($url);

		if ($this->containsIgnoredUrl($url)){
			return true;
		}

		if ($this->isAHomePage($url)){
			return true;
		}

		return false;
	}

	/**
	 * @param $url
	 * @return mixed
	 */
	private function removeUrlProtocol($url){
		$url = str_replace(['http://', 'https://'], '', $url);

		return $url;
	}

	private function containsIgnoredUrl($url){
		$ignored = [
			'twitter.com',
			't.co',
			'youtu.be',
			'www.youtube.com',
			'www.facebook.com',
			'facebook.com',
			'www.swarmapp.com',
			'www.meetup.com',
			'eventbrite.co.uk',
			'www.instagram.com',
		];

		$extra_ignored = $this->getParameter('extra_ignore_urls');
		$ignored = array_merge($extra_ignored, $ignored);

		foreach ($ignored as $ignore){
			if (stripos($url, $ignore)===0){
				return true;
			}
		}

		return false;
	}

	private function isAHomePage($url){
		$components = explode('//', $url);

		if (count($components)>2){
			// if there's more than 1 slash, then 3 or more components will
			// be returned and it's not likely to be a home page
			return false;
		}

		if (!empty($components[1])){
			// if the part after the slash has content it
			// is a page rather than a root page, so more likely
			// an article
			return false;
		}

		return true;
	}

	/**
	 * @param string $url
	 * @return string
	 */
	private function expandShortUrl($url){
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

	/**
	 * @return string
	 */
	private function getRecentFile(){
		$file_path = $this->getContainer()->get('kernel')->getRootDir();
		$file_name = $file_path.'/recent_tweet';

		return $file_name;
	}

	/**
	 * @param array $urls
	 * @param string $pocket_token
	 * @param OutputInterface $output
	 */
	private function addToPocket(array $urls, $pocket_token, OutputInterface $output){
		$pocket = $this->newPocket($pocket_token);

		foreach ($urls as $url){
			$url_no_protocol = $this->removeUrlProtocol($url);
			$domain = explode('/', $url_no_protocol)[0];

			$output->writeln("Searching for domain $domain");
			$items = $pocket->retrieve([
				'domain' => $domain,
				'state' => 'all',
			]);

			$add_to_pocket = true;
			foreach ($items['list'] as $item){
				if ($item['given_url']==$url){
					$output->writeln("Url $url is already added to Pocket");
					$add_to_pocket = false;
					break;
				}
			}

			if ($add_to_pocket){
				$output->writeln("Adding $url to Pocket");
				$pocket->add([
					'url' => $url,
				]);
			}
		}
	}

	/**
	 * @param string $access_token
	 *
	 * @return Pocket
	 */
	private function newPocket($access_token = ''){
		$pocket = new Pocket([
			'consumerKey' => $this->getParameter('pocket_key'),
		]);
		if (!empty($access_token)){
			$pocket->setAccessToken($access_token);
		}

		return $pocket;
	}

	/**
	 * @param array $statuses
	 */
	private function storeRecentTweet(array $statuses){
		$most_recent = reset($statuses);

		$id = $most_recent->id_str;

		$file_path = $this->getRecentFile();

		file_put_contents($file_path, $id);
	}

	/**
	 * @param string $twitter_token
	 * @param OutputInterface $output
	 * @return array
	 */
	protected function fetchTweets($twitter_token, OutputInterface $output){
		$connection = $this->getTwitter($twitter_token['oauth_token'], $twitter_token['oauth_token_secret']);

		$file_path = $this->getRecentFile();
		if (file_exists($file_path)){
			$recent_id = file_get_contents($file_path);
		}

		$params = ['exclude_replies' => true, 'count' => 100];

		if (!empty($recent_id)){
			$output->writeln("Only getting Tweets since $recent_id");
			$params['since_id'] = $recent_id;
		}

		$statuses = $connection->get('statuses/home_timeline', $params);

		return $statuses;
	}
}
