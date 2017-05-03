<?php

namespace Maci\AdminBundle\Twig;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpKernel\KernelInterface;

class MaciAdminExtension extends \Twig_Extension
{
    private $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public function getFunctions()
    {
        return array(
            'asset_exists' => new \Twig_Function_Method($this, 'asset_exists'),
            'is_image' => new \Twig_Function_Method($this, 'is_image'),
        );
    }

    public function asset_exists($path)
    {
        $webRoot = realpath($this->kernel->getRootDir() . '/../web/');
        $toCheck = realpath($webRoot . $path);

        // check if the file exists
        if (!is_file($toCheck))
        {
            return false;
        }

        // check if file is well contained in web/ directory (prevents ../ in paths)
        if (strncmp($webRoot, $toCheck, strlen($webRoot)) !== 0)
        { 
            return false;
        }

        return true;
    }

    public function is_image($path)
    {
        if (!$this->asset_exists($path))
        { 
            return false;
        }

        $file = realpath($this->kernel->getRootDir() . '/../web/' . $path);

        $smime = split('/', mime_content_type($file));

        if ($smime[0] === 'image') {
            return true;
        }

        return false;
    }

    public function getName()
    {
        return 'maci_admin_extension';
    }
}

