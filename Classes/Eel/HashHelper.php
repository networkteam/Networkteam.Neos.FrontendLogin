<?php
namespace Networkteam\Neos\FrontendLogin\Eel;

/***************************************************************
 *  (c) 2018 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Cryptography\HashService;

class HashHelper implements \Neos\Eel\ProtectedContextAwareInterface
{

    /**
     * @Flow\Inject
     * @var HashService
     */
    protected $hashService;

    /**
     * @param $string
     * @return string The string with appended HMAC
     */
    public function appendHmac($string)
    {
        return $this->hashService->appendHmac($string);
    }

    /**
     * Builds a associative array with the original value as key, and the HMAC secured string as value.
     *
     * @param array $array
     * @return array the given array of strings with each element HMAC appended
     */
    public function toHmacSecuredArray($array)
    {
        $result = array();
        foreach ($array as $value) {
            $result[$value] = $this->appendHmac($value);
        }
        return $result;
    }

    public function validate($string): bool
    {
        try {
            $this->hashService->validateAndStripHmac($string);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}