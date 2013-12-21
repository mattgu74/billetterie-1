<?php

namespace Payutc\OnyxBundle\CAS;

use \SimpleXMLElement;
use \Httpful\Request;

class Cas
{
    protected $url;
    protected $timeout;
    
    public function __construct($url, $timeout=10)
    {
        $this->url = $url;
        $this->timeout = $timeout;
    }
    
    public function authenticate($ticket, $service)
    {
        $r = Request::get($this->getValidateUrl($ticket, $service))
          ->sendsXml()
          ->timeoutIn($this->timeout)
          ->send();
        $r->body = str_replace("\n", "", $r->body);
        try {
            $xml = new SimpleXMLElement($r->body);
        }
        catch (\Exception $e) {
            throw new \Exception("Return cannot be parsed : '{$r->body}'");
        }
        
        $namespaces = $xml->getNamespaces();
        
        $serviceResponse = $xml->children($namespaces['cas']);
        $user = $serviceResponse->authenticationSuccess->user;
        
        if ($user) {
            return (string)$user; // cast simplexmlelement to string
        } else {
            $authFailed = $serviceResponse->authenticationFailure;
            if ($authFailed) {
                $attributes = $authFailed->attributes();
                //Log::warning("AuthenticationFailure : ".$attributes['code']." ($ticket, $service)");
                throw new \Exception((string)$attributes['code']);
            }
            else {
                //Log::error("Cas return is weird : '{$r->body}'");
                throw new \Exception($r->body);
            }
        }
    }
    
    public function getValidateUrl($ticket, $service)
    {
        return $this->url."serviceValidate?ticket=".urlencode($ticket)."&service=".urlencode($service);
    }
}
