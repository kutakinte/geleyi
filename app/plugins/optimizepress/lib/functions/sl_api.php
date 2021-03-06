<?php

/**
 * Class for handling API actions as well as security and licensing
 * @author Luka Peharda <luka.peharda@gmail.com>
 */
class OptimizePress_Sl_Api
{
	const OP_SL_BASE_URL = 'aHR0cDovL29wdGltaXplaHViLmNvbS9zbC9wdWJsaWMvYXBpLw==';
	const HEADER_INSTALLATION_URL_PARAM = 'Op-Installation-Url';
	const HEADER_API_KEY_PARAM = 'Op-Api-Key';
	const OPTION_API_KEY_PARAM = 'sl_api_key';

	/**
	 * @var OptimizePress_Sl_Api
	 */
	protected static $instance;

	/**
	 * Singleton pattern hidden constructor, initializes object
	 */
	private function __construct()
	{}

	/**
	 * Singleton pattern instance getter
	 * @return OptimizePress_Sl_Api
	 */
	public static function getInstance()
	{
		if (null === self::$instance) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Returns formated network_site_url()
	 * @return string
	 */
	protected function getInstallationUrl()
	{
		return site_url();
	}

	/**
	 * Fetching API key from options table
	 * @return [type] [description]
	 */
	public function getApiKey()
	{
		return op_get_option(self::OPTION_API_KEY_PARAM);
	}

	/**
	 * Saving API key to WP options table
	 * @param string $key
	 * @return void
	 */
	public function setApiKey($key)
	{
		op_update_option(self::OPTION_API_KEY_PARAM, $key);
	}

	/**
	 * Registers API key on OP SL
	 * @param  string $key
	 * @return bool|WP_Error      true on success, WP_Error on failure
	 */
	public function register($key)
	{
		$args = array(
			'headers' => array(
				self::HEADER_INSTALLATION_URL_PARAM => $this->getInstallationUrl()
			)
		);
		$response = wp_remote_get(base64_decode(self::OP_SL_BASE_URL) . 'register/' . $key, $args);

		if (is_wp_error($response)) {
			/*
			 * Request faild
			 */
			return new WP_Error($response->get_error_code(), $response->get_error_message());
		} else if ($response['response']['code'] !== 200) {
			/*
			 * API key issues
			 */
			$data = json_decode($response['body']);
			return new WP_Error('failed', $data->error);
		} else {
			/*
			 * Success
			 */
			$this->setApiKey($key);
			return true;
		}
	}

	/**
	 * Sends data to SL for parsing
	 * @param  string $type
	 * @param  array $data
	 * @return string
	 */
	public function parse($type, $data)
	{
		$args = array(
			'headers' => array(
				self::HEADER_INSTALLATION_URL_PARAM => $this->getInstallationUrl(),
				self::HEADER_API_KEY_PARAM => $this->getApiKey()
			),
			'body' => array(
				'data' => $data
			)
		);

		$response = wp_remote_post(base64_decode(self::OP_SL_BASE_URL) . 'parse/' . $type, $args);

		if (is_wp_error($response)) {
			/*
			 * Request failed
			 */
			$data = '';
		} else if ($response['response']['code'] != 200) {
			/*
			 * API key issues
			 */
			$data = '';
		} else {
			/*
			 * Success
			 */
			$json = json_decode($response['body'], true);
			$data = $json['data'];
		}

		return $data;
	}

	/**
	 * Ping SL service with API key
	 * @return bool|WP_Error
	 */
	public function ping()
	{
		$args = array(
			'headers' => array(
				self::HEADER_INSTALLATION_URL_PARAM => $this->getInstallationUrl(),
				self::HEADER_API_KEY_PARAM => $this->getApiKey()
			)
		);

		$response = wp_remote_get(base64_decode(self::OP_SL_BASE_URL) . 'ping', $args);	

		if (is_wp_error($response)) {
			/*
			 * Request failed
			 */
			return new WP_Error($response->get_error_code(), $response->get_error_message());
		} else if ($response['response']['code'] !== 200) {
			/*
			 * API key issues
			 */
			$data = json_decode($response['body']);
			return new WP_Error('invalid', $data->error);
		} else {
			/*
			 * Success
			 */
			return true;
		}
	}

	/**
	 * Check for latest theme/plugin version
	 * @param  string $type
	 * @return mixed
	 */
	public function update($type = 'theme')
	{
		$args = array(
			'headers' => array(
				self::HEADER_INSTALLATION_URL_PARAM => $this->getInstallationUrl(),
				self::HEADER_API_KEY_PARAM => $this->getApiKey()
			),
		);

		$response = wp_remote_get(base64_decode(self::OP_SL_BASE_URL) . 'update/' . $type, $args);

		if (is_wp_error($response)) {
			/*
			 * Request failed
			 */
			return new WP_Error($response->get_error_code(), $response->get_error_message());
		} else if ($response['response']['code'] !== 200) {
			/*
			 * API key issues
			 */
			$data = json_decode($response['body']);
			return new WP_Error('invalid', $data->error);
		} else {
			/*
			 * Success
			 */
			$data = json_decode($response['body']);
			return $data;
		}
	}
}

/**
 * Wrapper for saving API key to DB
 * @param  string $key
 * @return void
 */
function op_sl_save_key($key)
{
	OptimizePress_Sl_Api::getInstance()->setApiKey($key);
}

/**
 * Wrapper for fetching API key
 * @return string
 */
function op_sl_get_key()
{
	return OptimizePress_Sl_Api::getInstance()->getApiKey();
}

/**
 * Wrapper for ping method
 * @return bool|WP_Error
 */
function op_sl_ping()
{
	$status = OptimizePress_Sl_Api::getInstance()->ping();

	if (is_wp_error($status) && $status->get_error_code() === 'invalid') {

	}

	return $status;
}

/**
 * Wrapper for registration method
 * @param  string $key
 * @return bool|WP_Error
 */
function op_sl_register($key)
{
	return OptimizePress_Sl_Api::getInstance()->register($key);
}

/**
 * Wrapper for parsing method
 * @param  string $type
 * @param  array $data
 * @return string
 */
function op_sl_parse($type, $data)
{
	return OptimizePress_Sl_Api::getInstance()->parse($type, $data);
}

/**
 * Wrapper for update method
 * @param  string $type
 * @return string
 */
function op_sl_update($type)
{
	return OptimizePress_Sl_Api::getInstance()->update($type);
}