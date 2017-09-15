<?php

namespace yunwuxin\mail\twig\Node;

use Twig_Node;
use Twig_Node_Expression;
use Twig_Compiler;

class Component extends Twig_Node
{
    public function __construct(Twig_Node $body, Twig_Node_Expression $expr, Twig_Node_Expression $variables = null, $only = false, $ignoreMissing = false, $lineno, $tag = null)
    {
        $nodes = ['expr' => $expr, 'body' => $body];
        if (null !== $variables) {
            $nodes['variables'] = $variables;
        }

        parent::__construct($nodes, ['only' => (bool) $only, 'ignore_missing' => (bool) $ignoreMissing], $lineno, $tag);
    }

    public function compile(Twig_Compiler $compiler)
    {
        $compiler->addDebugInfo($this);

        if ($this->getAttribute('ignore_missing')) {
            $compiler
                ->write("try {\n")
                ->indent();
        }

        $compiler
            ->write('unset($context["slot"]);' . PHP_EOL)
            ->write('ob_start();' . PHP_EOL)
            ->subcompile($this->getNode('body'))
            ->write('$slot = ob_get_clean();' . PHP_EOL);

        $this->addGetTemplate($compiler);

        $compiler->raw('->display(');

        $this->addTemplateArguments($compiler);

        $compiler->raw(");\n");

        if ($this->getAttribute('ignore_missing')) {
            $compiler
                ->outdent()
                ->write("} catch (Twig_Error_Loader \$e) {\n")
                ->indent()
                ->write("// ignore missing template\n")
                ->outdent()
                ->write("}\n\n");
        }
    }

    protected function addGetTemplate(Twig_Compiler $compiler)
    {
        $compiler
            ->write('$this->loadTemplate(')
            ->subcompile($this->getNode('expr'))
            ->raw(', ')
            ->repr($this->getTemplateName())
            ->raw(', ')
            ->repr($this->getTemplateLine())
            ->raw(')');
    }

    protected function addTemplateArguments(Twig_Compiler $compiler)
    {

        $compiler->raw('array_merge(["slot"=>$slot],');

        if (!$this->hasNode('variables')) {
            $compiler->raw(false === $this->getAttribute('only') ? '$context' : 'array()');
        } elseif (false === $this->getAttribute('only')) {
            $compiler
                ->raw('$context, ')
                ->subcompile($this->getNode('variables'));
        } else {
            $compiler->subcompile($this->getNode('variables'));
        }

        $compiler->raw(')');
    }
}