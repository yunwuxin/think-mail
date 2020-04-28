<?php

namespace yunwuxin\mail\twig\Node;

use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;

class Component extends Node
{
    public function __construct(Node $body, AbstractExpression $expr, AbstractExpression $variables = null, $only = false, $ignoreMissing = false, $lineno = 0, $tag = null)
    {
        $nodes = ['expr' => $expr, 'body' => $body];
        if (null !== $variables) {
            $nodes['variables'] = $variables;
        }

        parent::__construct($nodes, ['only' => (bool) $only, 'ignore_missing' => (bool) $ignoreMissing], $lineno, $tag);
    }

    public function compile(Compiler $compiler)
    {
        $compiler->addDebugInfo($this);

        $this->addTemplateArguments($compiler);

        if ($this->getAttribute('ignore_missing')) {

            $template = $compiler->getVarName();

            $compiler
                ->write(sprintf("$%s = null;\n", $template))
                ->write("try {\n")
                ->indent()
                ->write(sprintf('$%s = ', $template));

            $this->addGetTemplate($compiler);

            $compiler
                ->raw(";\n")
                ->outdent()
                ->write("} catch (LoaderError \$e) {\n")
                ->indent()
                ->write("// ignore missing template\n")
                ->outdent()
                ->write("}\n")
                ->write(sprintf("if ($%s) {\n", $template))
                ->indent()
                ->write(sprintf('$%s->display($context', $template));

            $compiler
                ->raw(");\n")
                ->outdent()
                ->write("}\n");
        } else {
            $this->addGetTemplate($compiler);
            $compiler->raw('->display($context);');
            $compiler->raw("\n");
        }
    }

    protected function addGetTemplate(Compiler $compiler)
    {
        var_dump($this->getNode('expr'));
        $compiler
            ->write('$this->loadTemplate(')
            ->subcompile($this->getNode('expr'))
            ->raw('.".twig", ')
            ->repr($this->getTemplateName())
            ->raw(', ')
            ->repr($this->getTemplateLine())
            ->raw(')');
    }

    protected function addTemplateArguments(Compiler $compiler)
    {
        if (!$this->hasNode('variables')) {
            if (false !== $this->getAttribute('only')) {
                $compiler->write('$context = [];');
            }
        } elseif (false === $this->getAttribute('only')) {
            $compiler
                ->write('$context = twig_array_merge($context, ')
                ->subcompile($this->getNode('variables'))
                ->raw(');');
        } else {
            $compiler->write('$context = twig_to_array(');
            $compiler->subcompile($this->getNode('variables'));
            $compiler->raw(');');
        }
        $compiler->raw("\n");

        $compiler
            ->write("ob_start();\n")
            ->subcompile($this->getNode('body'))
            ->write('$context["slot"] = ob_get_clean();')
            ->raw("\n");
    }
}
