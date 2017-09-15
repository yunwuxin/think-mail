<?php

namespace yunwuxin\mail\twig;

class Loader extends \Twig_Loader_Filesystem
{

    protected function completeName($name)
    {
        if (!preg_match("/\.twig$/", $name)) {
            return $name . '.twig';
        }
        return $name;
    }

    public function getSourceContext($name)
    {
        return parent::getSourceContext($this->completeName($name));
    }

    public function getCacheKey($name)
    {
        return parent::getCacheKey($this->completeName($name));
    }

    public function isFresh($name, $time)
    {
        return parent::isFresh($this->completeName($name), $time);
    }

    public function exists($name)
    {
        return parent::exists($this->completeName($name));
    }
}