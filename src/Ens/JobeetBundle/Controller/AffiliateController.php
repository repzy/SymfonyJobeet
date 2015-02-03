<?php

namespace Ens\JobeetBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Ens\JobeetBundle\Entity\Affiliate;
use Ens\JobeetBundle\Form\AffiliateType;
use Symfony\Component\HttpFoundation\Request;
use Ens\JobeetBundle\Entity\Category;

class AffiliateController extends Controller
{
    public function newAction()
    {
        $entity = new Affiliate();
        $form = $this->createForm(new AffiliateType(), $entity);

        return $this->render('EnsJobeetBundle:Affiliate:new.html.twig', array(
            'entity' => $entity,
            'form' =>$form->createView(),
        ));
    }

    public function createAction(Request $request)
    {
        $affiliate = new Affiliate();
        $form = $this->createForm(new AffiliateType(), $affiliate);
        $form->bind($request);
        $em = $this->getDoctrine()->getManager();

        if ($form->isValid()) {
            $formData = $request->get('affiliate');
            $affiliate->setUrl($formData['url']);
            $affiliate->setEmail($formData['email']);
            $affiliate->setIsActive(false);

            $em->persist($affiliate);
            $em->flush();

            return $this->redirect($this->generateUrl('ens_affiliate_wait'));
        }

        return $this->render('EnsJobeetBundle:Affiliate:new.html.twig', array(
            'entity' => $affiliate,
            'form' => $form->createView(),
        ));
    }

    public function waitAction()
    {
        return $this->render('EnsJobeetBundle:Affiliate:wait.html.twig');
    }
}