<?php

namespace yunwuxin\mail\twig\TokenParser;

use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use Twig\TokenStream;
use yunwuxin\mail\twig\Node\Component as ComponentNode;

class Component extends AbstractTokenParser
{

    /**
     * @param Token $token
     * @return ComponentNode
     */
    public function parse(Token $token)
    {
        $expr = $this->parser->getExpressionParser()->parseExpression();

        $stream = $this->parser->getStream();

        list($variables, $only, $ignoreMissing) = $this->parseArguments($stream);

        $stream->expect(Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse([$this, 'decideBlockEnd'], true);
        $stream->expect(Token::BLOCK_END_TYPE);

        return new ComponentNode($body, $expr, $variables, $only, $ignoreMissing, $token->getLine(), $this->getTag());
    }

    public function decideBlockEnd(Token $token)
    {
        return $token->test('endcomponent');
    }

    protected function parseArguments(TokenStream $stream)
    {
        $ignoreMissing = false;
        if ($stream->nextIf(Token::NAME_TYPE, 'ignore')) {
            $stream->expect(Token::NAME_TYPE, 'missing');

            $ignoreMissing = true;
        }

        $variables = null;
        if ($stream->nextIf(Token::NAME_TYPE, 'with')) {
            $variables = $this->parser->getExpressionParser()->parseExpression();
        }

        $only = false;
        if ($stream->nextIf(Token::NAME_TYPE, 'only')) {
            $only = true;
        }

        return [$variables, $only, $ignoreMissing];
    }

    public function getTag()
    {
        return 'component';
    }
}
