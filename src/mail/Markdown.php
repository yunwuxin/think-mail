<?php

namespace yunwuxin\mail;

use cebe\markdown\GithubMarkdown;

class Markdown extends GithubMarkdown
{

    protected function identifyCode($line)
    {
        return false;
    }
}