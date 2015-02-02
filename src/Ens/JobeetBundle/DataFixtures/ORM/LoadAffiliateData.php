<?php

namespace Ens\JobeetBundle\DataFixtures\ORM;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Ens\JobeetBundle\Entity\Affiliate;

class LoadAffiliateData extends AbstractFixture implements OrderedFixtureInterface
{

    /**
     * Load data fixtures with the passed EntityManager
     *
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $em)
    {
        $affiliate = new Affiliate();

        $affiliate->setUrl('http://sensio-labs.com');
        $affiliate->setEmail('adress1@example.com');
        $affiliate->setToken('sensio-labs');
        $affiliate->setIsActive(true);
        //$affiliate->addCategorie($em->merge($this->getReference('category-programming')));

        $em->persist($affiliate);

        $affiliate = new Affiliate();

        $affiliate->setUrl('/');
        $affiliate->setEmail('adress2@example.com');
        $affiliate->setToken('symfony');
        $affiliate->setIsActive(false);
        //$affiliate->addCategorie($em->merge($this->getReference('category-programming')), $em->merge($this->getReference('category-design')));

        $em->persist($affiliate);
        $em->flush();

        $this->addReference('affiliate', $affiliate);
    }

    /**
     * Get the order of this fixture
     *
     * @return integer
     */
    public function getOrder()
    {
        return 3;
    }
}
