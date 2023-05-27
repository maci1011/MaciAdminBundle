<?php

namespace Maci\AdminBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
	public function setInheritConfig($rootNode)
	{
		$rootNode
			->arrayNode('config')
				->children()
					->booleanNode('controller')->end()
					->booleanNode('enabled')->end()
					->scalarNode('page_limit')->end()
					->scalarNode('page_range')->end()
					->arrayNode('roles')
						->prototype('scalar')->end()
					->end()
					->arrayNode('deny_fields')
						->prototype('scalar')->end()
					->end()
					->booleanNode('sortable')->end()
					->scalarNode('sort_field')->end()
					->arrayNode('actions')
						->prototype('array')
							->beforeNormalization()
								->ifString()
								->then(function($v) { return ['template' => $v]; })
							->end()
							->children()
								->scalarNode('template')->end()
								->scalarNode('controller')->end()
								->booleanNode('enabled')->end()
							->end()
						->end()
					->end()
					->booleanNode('trash')->end()
					->scalarNode('trash_field')->end()
					->booleanNode('uploadable')->end()
					->scalarNode('upload_field')->end()
					->scalarNode('upload_path_field')->end()
				->end()
			->end()
		;
	}

	public function setViewNodes($rootNode)
	{
		$rootNode
			->scalarNode('label')->end()
			->arrayNode('list')
				->beforeNormalization()
					->ifString()
					->then(function($v) { return [$v]; })
				->end()
				->prototype('scalar')->end()
			->end()
			->arrayNode('bridges')
				->beforeNormalization()
					->ifString()
					->then(function($v) { return [$v]; })
				->end()
				->prototype('scalar')->end()
			->end()
			->arrayNode('filters')
				->prototype('array')
					->beforeNormalization()
						->ifString()
						->then(function($v) { return ['value' => $v]; })
					->end()
					->children()
						->scalarNode('connector')->end()
						->scalarNode('method')->end()
						->scalarNode('value')->isRequired()->end()
						->arrayNode('ex_roles')
							->beforeNormalization()
								->ifString()
								->then(function($v) { return [$v]; })
							->end()
							->prototype('scalar')->end()
						->end()
					->end()
				->end()
			->end()
		;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getConfigTreeBuilder()
	{
		$treeBuilder = new TreeBuilder('maci_admin');
		$rootNode = $treeBuilder->getRootNode();

		$currentRoot = $rootNode
			->children();
				$this->setInheritConfig($currentRoot);
				$currentRoot = $currentRoot
				->arrayNode('sections')
					->prototype('array')
						->children();
							$this->setInheritConfig($currentRoot);
							$currentRoot = $currentRoot
							->scalarNode('dashboard')->end()
							->scalarNode('label')->end()
							->arrayNode('entities')
								->prototype('array')
									->beforeNormalization()
										->ifString()
										->then(function($v) { return ['class' => $v]; })
									->end()
									->children();
										$this->setInheritConfig($currentRoot);
										$this->setViewNodes($currentRoot);
										$currentRoot = $currentRoot
										->scalarNode('class')->isRequired()->end()
										->scalarNode('form')->end()
										->arrayNode('relations')
											->prototype('array')
												->children();
													$this->setInheritConfig($currentRoot);
													$this->setViewNodes($currentRoot);
													$currentRoot = $currentRoot
												->end()
											->end()
										->end()
									->end()
								->end()
							->end()
							->arrayNode('pages')
								->prototype('array')
									->beforeNormalization()
										->ifString()
										->then(function($v) { return ['route' => $v]; })
									->end()
									->children()
										->scalarNode('route')->isRequired()->end()
										->arrayNode('params')->end()
										->arrayNode('roles')
											->prototype('scalar')->end()
										->end()
									->end()
								->end()
							->end()
						->end()
					->end()
				->end()
			->end()
		;

		return $treeBuilder;
	}
}
