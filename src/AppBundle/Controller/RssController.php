<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Rss;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class RssController extends Controller {
    /**
     * @Route("/listRss", name="listRss")
     */
    public function listAction(){
        $rss = new Rss();

        $form = $this->createFormBuilder($rss)
            ->setAction($this->generateUrl('addRss'))
            ->add('url', TextType::class)
            ->add('save', SubmitType::class, ['label' => 'Submit URL'])
            ->getForm();

        $rss_feeds = $this->rssFeedsFromFile();

        return $this->render('AppBundle:Rss:list.html.twig', [
            'form' => $form->createView(),
            'rss_feeds' => $rss_feeds,
        ]);
    }

    /**
     * @Route("/viewRss", name="viewRss")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewAction(Request $request){
        $key = $request->query->get('key');

        $rss_feeds = $this->rssFeedsFromFile();

        $selected_feed = $rss_feeds[$key];
        if (empty($selected_feed)){
            throw new BadRequestHttpException('The URL for the RSS feed is not found');
        }

        return $this->render('AppBundle:Rss:view.html.twig', [
            'address' => $selected_feed['address'],
        ]);
    }

    /**
     * @Route("/addRss", name="addRss")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function addAction(Request $request){
        $url = $request->request->get('form')['url'];
        file_put_contents($this->getFileName(), $url."\n", FILE_APPEND);

        return $this->redirectToRoute('listRss');
    }

    /**
     * @return string
     */
    private function getFileName(){
        $file_path = $this->get('kernel')->getRootDir();
        $file_name = $file_path.'/rss';

        return $file_name;
    }

    /**
     * @return array
     */
    private function rssFeedsFromFile(){
        $rss_feed_urls = file_get_contents($this->getFileName());
        $rss_feed_urls = explode("\n", $rss_feed_urls);

        $rss_feeds = [];
        foreach ($rss_feed_urls as $key => $rss_feed_url){
            if (empty($rss_feed_url)){
                continue;
            }

            $rss_feeds[] = [
                'address' => $rss_feed_url,
                'url_view' => $this->generateUrl('viewRss').'?key='.$key
            ];
        }

        return $rss_feeds;
    }

}
