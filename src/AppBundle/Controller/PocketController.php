<?php

namespace AppBundle\Controller;

use AppBundle\Entity\PocketUrl;
use Pocket;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PocketController extends Controller {
	const SESSION_POCKET_AUTH = 'pocket_auth';
	const SESSION_POCKET_TOKEN = 'pocket_token';
	const COOKIE_POCKET_TOKEN = 'pocket_token';

	/**
	 * @Route("/sendToPocket", name="sendToPocket")
	 * @Method("POST")
	 *
	 * @param Request $request
	 *
	 * @return RedirectResponse
	 */
	public function sendToPocketAction(Request $request){
		$access_token = $this->accessTokenFromRequest($request);
		$pocket = $this->newPocket($access_token);

		$url = $request->request->get('form')['url'];
		$pocket->add([
			'url' => $url,
		], $access_token);

		$response = $this->redirectToRoute('pocketForm', [], 301);

		return $response;
	}

	/**
	 * @Route("/pocketForm", name="pocketForm")
	 * @Method("GET")
	 *
	 * @param Request $request
	 *
	 * @return Response
	 */
	public function pocketFormAction(Request $request){
		$pocket_url = new PocketUrl();

		$url_send_to_pocket = $this->generateUrl('sendToPocket');
		$form = $this->createFormBuilder($pocket_url)
			->setAction($url_send_to_pocket)
			->add('url', TextType::class)
			->add('save', SubmitType::class, ['label' => 'Submit URL'])
			->getForm();

		$access_token = $this->retrieveAccessToken($request);

		$url_pocket_auth = $this->generateUrl('pocketAuth');
		$response = $this->render('AppBundle:Pocket:pocket_form.html.twig', [
			'form' => $form->createView(),
			'url_pocket_auth' => $url_pocket_auth,
			'access_token' => $access_token,
		]);

		return $response;
	}

	/**
	 * @Route("/pocketAuth", name="pocketAuth")
	 * @Method("GET")
	 *
	 * @param Request $request
	 *
	 * @return RedirectResponse
	 */
	public function pocketAuthAction(Request $request){
		$pocket = $this->newPocket();

		$return_url = $this->generateAbsoluteUrl('pocketAuthReturn');
		$pocket_redir = $pocket->requestToken($return_url);

		$request->getSession()->set(self::SESSION_POCKET_AUTH, $pocket_redir['request_token']);

		return $this->redirect($pocket_redir['redirect_uri']);
	}

	/**
	 * @Route("/pocketAuthReturn", name="pocketAuthReturn")
	 * @Method("GET")
	 *
	 * @param Request $request
	 *
	 * @return RedirectResponse
	 */
	public function pocketAuthReturnAction(Request $request){
		$pocket_auth = $request->getSession()->get(self::SESSION_POCKET_AUTH);

		$pocket = $this->newPocket();

		$pocket_convert = $pocket->convertToken($pocket_auth);

		$redirect_url = $this->generateAbsoluteUrl('pocketForm');
		$response = new RedirectResponse($redirect_url);

		$access_token = $pocket_convert['access_token'];
		$cookie = new Cookie(self::COOKIE_POCKET_TOKEN, $access_token, 3600 * 24);
		$response->headers->setCookie($cookie);
		$request->getSession()->set(self::SESSION_POCKET_TOKEN, $access_token);

		$this->saveAccessTokenToFile($access_token);

		return $response;
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
	 * @param Request $request
	 *
	 * @return string
	 */
	private function accessTokenFromRequest(Request $request){
		$auth_token = $request->getSession()->get(self::SESSION_POCKET_TOKEN);

		return $auth_token;
	}

	/**
	 * @param string $access_token
	 */
	private function saveAccessTokenToFile($access_token){
		$file_name = $this->getFileName();

		file_put_contents($file_name, $access_token);
	}

	/**
	 * @return string
	 */
	private function accessTokenFromFile(){
		$file_name = $this->getFileName();

		return file_get_contents($file_name);
	}

	/**
	 * @return string
	 */
	private function getFileName(){
		$file_path = $this->get('kernel')->getRootDir();
		$file_name = $file_path.'/access_token';

		return $file_name;
	}

	/**
	 * @param Request $request
	 *
	 * @return string
	 */
	private function retrieveAccessToken(Request $request){
		$access_token = $this->accessTokenFromRequest($request);
		if (!empty($access_token)){
			$this->saveAccessTokenToFile($access_token);

			return $access_token;
		}

		$access_token = $this->accessTokenFromFile();

		return $access_token;
	}
}
