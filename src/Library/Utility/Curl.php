<?php

/**
 * For fetching data by URL.
 *
 * http://php.net/manual/en/function.curl-setopt.php
 */

namespace Cache\Library\Utility;

class Curl
{
    /**
     * An array of default options for Curl.
     *
     * @var array
     */
    private $defaultOpts = array(
        CURLOPT_VERBOSE => false,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_RETURNTRANSFER => true
    );

    /**
     * The URL to request from.
     *
     * @var string
     */
    private $url;

    /**
     * The HTTP code from the request.
     *
     * @var integer
     */
    private $httpCode;

    /**
     * The headers of the request.
     *
     * @var array
     */
    private $headers;

    /**
     * The body of the request.
     *
     * @var string
     */
    private $body;

    /**
     * Constructor.
     *
     * @param string $url The URL to request from
     */
    public function __construct($url)
    {
        $this->url = $url;
        $this->fetch();
    }

    /**
     * Gets the array of default options for Curl.
     *
     * @return array
     */
    public function getDefaultOpts()
    {
        return $this->defaultOpts;
    }

    /**
     * Gets the URL to request from.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Sets the HTTP code from the request.
     *
     * @param integer $httpCode The http code
     * @return self
     */
    public function setHttpCode($httpCode)
    {
        $this->httpCode = $httpCode;

        return $this;
    }

    /**
     * Gets the HTTP code from the request.
     *
     * @return integer
     */
    public function getHttpCode()
    {
        return $this->httpCode;
    }

    /**
     * Sets the headers of the request.
     *
     * @param array $headers The headers
     * @return self
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * Gets the headers of the request.
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Sets the body of the request.
     *
     * @param string $body The body
     * @return self
     */
    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Gets the body of the request.
     *
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Return an array of information about this request excluding the body. If the
     * request wasn't successful, go ahead and provide the body, as it might contain
     * a helpful message if an error occurred.
     *
     * @return array
     */
    public function getInfo()
    {
        $info = array(
            'http_code' => $this->getHttpCode(),
            'headers' => $this->getHeaders()
        );

        if (!$this->isSuccessful()) {
            $info['body'] = $this->getBody();
        }

        return $info;
    }

    /**
     * Fetch data from the URL.
     *
     * @return self
     */
    private function fetch()
    {
        $curl = curl_init();

        $opts = $this->getDefaultOpts() + array(CURLOPT_URL => $this->getUrl());
        curl_setopt_array($curl, $opts);

        $response = curl_exec($curl);

        if (!curl_errno($curl)) {
            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $this->setHttpCode(curl_getinfo($curl, CURLINFO_HTTP_CODE));
            $this->setHeaders(self::parseHeaders($response, $headerSize));
            $this->setBody(self::parseBody($response, $headerSize));
        }

        curl_close($curl);

        return $this;
    }

    /**
     * Determine whether a request was successful by HTTP code. Here, success means
     * HTTP codes 2xx and 3xx.
     *
     * @return boolean
     */
    public function isSuccessful()
    {
        return in_array(substr($this->getHttpCode(), 0, 1), array(2, 3));
    }

    /**
     * Parse the headers from a Curl response.
     *
     * @param  string  $response   The entire Curl response
     * @param  integer $headerSize The size of the headers of the response
     * @return array
     */
    private static function parseHeaders($response, $headerSize)
    {
        $headers = array();
        $headerString = trim(substr($response, 0, $headerSize));
        $headerLines = explode("\n", $headerString);

        foreach ($headerLines as $line) {
            list($key, $val) = explode(':', $line, 2);
            $headers[trim($key)] = trim($val);
        }

        return $headers;
    }

    /**
     * Parse the body from a Curl response.
     *
     * @param  string  $response   The entire Curl response
     * @param  integer $headerSize The size of the headers of the response
     * @return string
     */
    private static function parseBody($response, $headerSize)
    {
        return trim(substr($response, $headerSize));
    }

}
