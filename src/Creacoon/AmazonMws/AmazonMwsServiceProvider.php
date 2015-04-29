<?php namespace Creacoon\AmazonMws;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\AliasLoader;

class AmazonMwsServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		//
	}

	public function boot()
	{
		$this->package('creacoon/amazon-mws');

		AliasLoader::getInstance()->alias('AmazonOrderList', 'Creacoon\AmazonMws\AmazonOrderList');
		AliasLoader::getInstance()->alias('AmazonOrderItemList', 'Creacoon\AmazonMws\AmazonOrderItemList');
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}
