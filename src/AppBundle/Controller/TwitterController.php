<?php

namespace AppBundle\Controller;

use Abraham\TwitterOAuth\TwitterOAuth;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TwitterController extends Controller {
	const SESSION_TWITTER_AUTH = 'twitter_auth';
	const COOKIE_TWITTER_TOKEN = 'twitter_auth';

	/**
	 * @Route("/twitterAuthRedirect", name="twitterAuthRedirect")
	 * @Method("GET")
	 *
	 * @param Request $request
	 *
	 * @return RedirectResponse
	 */
	public function twitterAuthRedirectAction(Request $request){
		$connection = $this->getTwitter();

		$return_url = $this->generateAbsoluteUrl('twitterAuthReturn');

		$twitter_request_token = $connection->oauth('oauth/request_token', ['oauth_callback' => $return_url]);

		$twitter_auth_url = $connection->url('oauth/authorize', ['oauth_token' => $twitter_request_token['oauth_token'],]);

		$request->getSession()->set(self::SESSION_TWITTER_AUTH, $twitter_request_token['oauth_token_secret']);

		return $this->redirect($twitter_auth_url);
	}

	/**
	 * @Route("/twitterAuthReturn", name="twitterAuthReturn")
	 * @Method("GET")
	 *
	 * @param Request $request
	 *
	 * @return RedirectResponse
	 */
	public function twitterAuthReturnAction(Request $request){
		$query = $request->query;
		$twitter_token = $query->get('oauth_token');
		$twitter_token_secret = $request->getSession()->get(self::SESSION_TWITTER_AUTH);

		$connection = $this->getTwitter($twitter_token, $twitter_token_secret);
		$twitter_access_token = $connection->oauth('oauth/access_token', ['oauth_verifier' => $query->get('oauth_verifier')]);

		$file_path = $this->getFileName();

		file_put_contents($file_path, json_encode($twitter_access_token));

		$redirect_url = $this->generateAbsoluteUrl('twitterList');
		$response = new RedirectResponse($redirect_url);

		return $response;
	}

	/**
	 * @Route("/twitterList", name="twitterList")
	 * @Method("GET")
	 *
	 * @return Response
	 */
	public function twitterListAction(){
		$token_store = $this->getStoredToken();

		if (!empty($token_store)){
			$connection = $this->getTwitter($token_store['oauth_token'], $token_store['oauth_token_secret']);
			$statuses = $connection->get('statuses/home_timeline', ['exclude_replies' => true, 'count' => 100]);
			$statuses = $this->expandUrls($statuses);
		}
		else {
			$statuses = [];
		}

		$url_twitter_auth = $this->generateUrl('twitterAuthRedirect');
		$response = $this->render('AppBundle:Twitter:twitter_list.html.twig', [
			'tweets' => $statuses,
			'url_twitter_auth' => $url_twitter_auth,
			'access_token' => $token_store,
		]);

		return $response;
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
	 * @param array $statuses
	 * @return array
	 */
	private function expandUrls(array $statuses){
		foreach ($statuses as &$tweet){
			$urls = $tweet->entities->urls;
			$parsed_urls = [];

			if (empty($urls)){
				$tweet->parsed_urls = $parsed_urls;
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
			$tweet->parsed_urls = $parsed_urls;
		}

		return $statuses;
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
	 * @param string $route
	 *
	 * @return string
	 */
	private function generateAbsoluteUrl($route){
		$url = $this->generateUrl($route);
		$url_absolute = $this->absoluteUrl($url);

		return $url_absolute;
	}

	/**
	 * @param string $url
	 *
	 * @return string
	 */
	private function absoluteUrl($url){
		if ($url[0]!=='/'){
			$url = '/'.$url;
		}

		return 'http://localhost'.$url;
	}

	/**
	 * @return string
	 */
	private function getFileName(){
		$file_path = $this->get('kernel')->getRootDir();
		$file_name = $file_path.'/token_twitter';

		return $file_name;
	}

	/**
	 * @return array|string
	 */
	private function getStoredToken(){
		$file_path = $this->getFileName();
		$token_store = file_exists($file_path) ? file_get_contents($file_path) : '';
		$token_store = json_decode($token_store, true) ?: [];

		return $token_store;
	}
}
