<?php

namespace Payutc\OnyxBundle\CAS;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Payutc\OnyxBundle\CAS\Cas;
use Ginger\Client\GingerClient;
use Doctrine\ORM\EntityManager;
use Payutc\OnyxBundle\Entity\User;
use Payutc\OnyxBundle\Entity\UserRepository;

class CASUserProvider implements UserProviderInterface
{
    private $em;
 
    public function __construct(EntityManager $entityManager, $encoderFactory)
    {
        $this->em = $entityManager;
        $this->factory = $encoderFactory;
    }

    public function loadUserByUsername($username)
    {
        global $_GET;
        
        if($username != "NONE_PROVIDED" || !array_key_exists("ticket", $_GET)) {
            // Throw any Exception otherwise
            die("NOT CAS");
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));        
        }
        
        // Call the CAS service here
        try {
            $cas = new Cas("https://cas.utc.fr/cas/");
            $login = $cas->authenticate($_GET['ticket'], "http://localhost/onyx/web/login_check");
        } catch (\Exception $e) {
            die("CAS ERROR");
            throw new UsernameNotFoundException("CAS ERROR");        
        }
        
        // Call Ginger (to get more information (we only have login their))
        $ginger = new GingerClient("fauxginger", "http://localhost/faux-ginger/index.php/v1/");
		$userInfo = $ginger->getUser($login);
        
        $user = $this->em
            ->getRepository('PayutcOnyxBundle:User')
            ->loadUserByUsername($userInfo->mail);

        if (!$user) {
            // User doesn't already exist, we need to create him an account
            $user = new User();
            $user->setEmail($userInfo->mail);
            $user->setFirstname($userInfo->prenom);
            $user->setName($userInfo->nom);

            // TODO: Generate a better password, than $login and send it by email.
            $password = $userInfo->login;
            $user->setPassword($password);
            $encoder = $this->factory->getEncoder($user);
            $user->encryptPassword($encoder);
            
            $this->em->persist($user);
            $this->em->flush();
        }

        if ($user) {
            //return $user;
            //die($user->getUsername());
            $userRepo = $this->em->getRepository('PayutcOnyxBundle:User');
            return $userRepo->loadUserByUsername($user->getUsername());
        }

        // Throw any Exception otherwise
        throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
    }

    public function refreshUser(UserInterface $user)
    {
        die('refreshUser');
        throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
    }

    public function supportsClass($class)
    {
        return $class === 'Payutc\Onyx\CAS\CASUser';
    }
}
