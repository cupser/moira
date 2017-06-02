<?php

class Input {
    
    /**
     * IP address of the current user
     *
     * @var	string
     */
    protected $ip_address = FALSE;

    /**
     * Allow GET array flag
     *
     * If set to FALSE, then $_GET will be set to an empty array.
     *
     * @var	bool
     */
    protected $_allow_get_array = TRUE;

    /**
     * Standardize new lines flag
     *
     * If set to TRUE, then newlines are standardized.
     *
     * @var	bool
     */
    protected $_standardize_newlines;

    /**
     * Enable XSS flag
     *
     * Determines whether the XSS filter is always active when
     * GET, POST or COOKIE data is encountered.
     * Set automatically based on config setting.
     *
     * @var	bool
     */
    protected $_enable_xss = FALSE;

    /**
     * Enable CSRF flag
     *
     * Enables a CSRF cookie token to be set.
     * Set automatically based on config setting.
     *
     * @var	bool
     */
    protected $_enable_csrf = FALSE;

    /**
     * List of all HTTP request headers
     *
     * @var array
     */
    protected $headers = array();

    /**
     * Raw input stream data
     *
     * Holds a cache of php://input contents
     *
     * @var	string
     */
    protected $_raw_input_stream;

    /**
     * Parsed input stream data
     *
     * Parsed from php://input at runtime
     *
     * @see	Input::input_stream()
     * @var	array
     */
    protected $_input_stream;
    
    protected $_json_stream;

    /**
     * List of never allowed strings
     *
     * @var	array
     */
    protected $_never_allowed_str = array(
        'document.cookie' => '[removed]',
        'document.write' => '[removed]',
        '.parentNode' => '[removed]',
        '.innerHTML' => '[removed]',
        '-moz-binding' => '[removed]',
        '<!--' => '&lt;!--',
        '-->' => '--&gt;',
        '<![CDATA[' => '&lt;![CDATA[',
        '<comment>' => '&lt;comment&gt;'
    );

    /**
     * List of never allowed regex replacements
     *
     * @var	array
     */
    protected $_never_allowed_regex = array(
        'javascript\s*:',
        '(document|(document\.)?window)\.(location|on\w*)',
        'expression\s*(\(|&\#40;)', // CSS and IE
        'vbscript\s*:', // IE, surprise!
        'wscript\s*:', // IE
        'jscript\s*:', // IE
        'vbs\s*:', // IE
        'Redirect\s+30\d',
        "([\"'])?data\s*:[^\\1]*?base64[^\\1]*?,[^\\1]*?\\1?"
    );

    // --------------------------------------------------------------------

    /**
     * Class constructor
     *
     * Determines whether to globally enable the XSS processing
     * and whether to allow the $_GET array.
     *
     * @return	void
     */
    public function __construct() {
        $this->_allow_get_array = (config_item('server.allow_get_array') == TRUE);
        $this->_enable_xss = (config_item('server.global_xss_filtering') == TRUE);
        $this->_standardize_newlines = (bool) config_item('server.standardize_newlines');

        // Sanitize global arrays
        $this->_sanitize_globals();

    }

    // --------------------------------------------------------------------

    /**
     * Fetch from array
     *
     * Internal method used to retrieve values from global arrays.
     *
     * @param	array	&$array		$_GET, $_POST, $_COOKIE, $_SERVER, etc.
     * @param	mixed	$index		Index for item to be fetched from $array
     * @param	bool	$xss_clean	Whether to apply XSS filtering
     * @return	mixed
     */
    protected function _fetch_from_array(&$array, $index = NULL, $xss_clean = NULL) {
        is_bool($xss_clean) OR $xss_clean = $this->_enable_xss;

        // If $index is NULL, it means that the whole $array is requested
        isset($index) OR $index = array_keys($array);

        // allow fetching multiple keys at once
        if (is_array($index)) {
            $output = array();
            foreach ($index as $key) {
                $output[$key] = $this->_fetch_from_array($array, $key, $xss_clean);
            }

            return $output;
        }

        if (isset($array[$index])) {
            $value = $array[$index];
        } elseif (($count = preg_match_all('/(?:^[^\[]+)|\[[^]]*\]/', $index, $matches)) > 1) { // Does the index contain array notation
            $value = $array;
            for ($i = 0; $i < $count; $i++) {
                $key = trim($matches[0][$i], '[]');
                if ($key === '') { // Empty notation will return the value as array
                    break;
                }

                if (isset($value[$key])) {
                    $value = $value[$key];
                } else {
                    return NULL;
                }
            }
        } else {
            return NULL;
        }

        return ($xss_clean === TRUE) ? $this->xss_clean($value) : $value;
    }

    // --------------------------------------------------------------------

    /**
     * Fetch an item from the GET array
     *
     * @param	mixed	$index		Index for item to be fetched from $_GET
     * @param	bool	$xss_clean	Whether to apply XSS filtering
     * @return	mixed
     */
    public function get($index = NULL, $xss_clean = NULL) {
        return $this->_fetch_from_array($_GET, $index, $xss_clean);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch an item from the POST array
     *
     * @param	mixed	$index		Index for item to be fetched from $_POST
     * @param	bool	$xss_clean	Whether to apply XSS filtering
     * @return	mixed
     */
    public function post($index = NULL, $xss_clean = NULL) {
        return $this->_fetch_from_array($_POST, $index, $xss_clean);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch an item from POST data with fallback to GET
     *
     * @param	string	$index		Index for item to be fetched from $_POST or $_GET
     * @param	bool	$xss_clean	Whether to apply XSS filtering
     * @return	mixed
     */
    public function post_get($index, $xss_clean = NULL) {
        return isset($_POST[$index]) ? $this->post($index, $xss_clean) : $this->get($index, $xss_clean);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch an item from GET data with fallback to POST
     *
     * @param	string	$index		Index for item to be fetched from $_GET or $_POST
     * @param	bool	$xss_clean	Whether to apply XSS filtering
     * @return	mixed
     */
    public function get_post($index, $xss_clean = NULL) {
        return isset($_GET[$index]) ? $this->get($index, $xss_clean) : $this->post($index, $xss_clean);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch an item from the COOKIE array
     *
     * @param	mixed	$index		Index for item to be fetched from $_COOKIE
     * @param	bool	$xss_clean	Whether to apply XSS filtering
     * @return	mixed
     */
    public function cookie($index = NULL, $xss_clean = NULL) {
        return $this->_fetch_from_array($_COOKIE, $index, $xss_clean);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch an item from the SERVER array
     *
     * @param	mixed	$index		Index for item to be fetched from $_SERVER
     * @param	bool	$xss_clean	Whether to apply XSS filtering
     * @return	mixed
     */
    public function server($index, $xss_clean = NULL) {
        return $this->_fetch_from_array($_SERVER, $index, $xss_clean);
    }

    // ------------------------------------------------------------------------

    /**
     * Fetch an item from the php://input stream
     *
     * Useful when you need to access PUT, DELETE or PATCH request data.
     *
     * @param	string	$index		Index for item to be fetched
     * @param	bool	$xss_clean	Whether to apply XSS filtering
     * @return	mixed
     */
    public function input_stream($index = NULL, $xss_clean = NULL) {
        // Prior to PHP 5.6, the input stream can only be read once,
        // so we'll need to check if we have already done that first.
        if (!is_array($this->_input_stream)) {
            // $this->raw_input_stream will trigger __get().
            parse_str($this->raw_input_stream, $this->_input_stream);
            is_array($this->_input_stream) OR $this->_input_stream = array();
        }

        return $this->_fetch_from_array($this->_input_stream, $index, $xss_clean);
    }

    public function json_stream($index = NULL, $xss_clean = NULL){
        if (!is_array($this->_json_stream)) {
            // $this->raw_input_stream will trigger __get().
            $this->_json_stream = json_decode($this->raw_input_stream, true);
            is_array($this->_json_stream) OR $this->_json_stream = array();
        }

        return $this->_fetch_from_array($this->_json_stream, $index, $xss_clean);
    }
    
    public function input_content(){
        return $this->raw_input_stream;
    }
    
    // ------------------------------------------------------------------------

    /**
     * Set cookie
     *
     * Accepts an arbitrary number of parameters (up to 7) or an associative
     * array in the first parameter containing all the values.
     *
     * @param	string|mixed[]	$name		Cookie name or an array containing parameters
     * @param	string		$value		Cookie value
     * @param	int		$expire		Cookie expiration time in seconds
     * @param	string		$domain		Cookie domain (e.g.: '.yourdomain.com')
     * @param	string		$path		Cookie path (default: '/')
     * @param	string		$prefix		Cookie name prefix
     * @param	bool		$secure		Whether to only transfer cookies via SSL
     * @param	bool		$httponly	Whether to only makes the cookie accessible via HTTP (no javascript)
     * @return	void
     */
    public function set_cookie($name, $value = '', $expire = '', $domain = '', $path = '/', $prefix = '', $secure = FALSE, $httponly = FALSE) {
        if (is_array($name)) {
            // always leave 'name' in last place, as the loop will break otherwise, due to $$item
            foreach (array('value', 'expire', 'domain', 'path', 'prefix', 'secure', 'httponly', 'name') as $item) {
                if (isset($name[$item])) {
                    $$item = $name[$item];
                }
            }
        }

        if ($prefix === '' && config_item('server.cookie_prefix') !== '') {
            $prefix = config_item('server.cookie_prefix');
        }

        if ($domain == '' && config_item('server.cookie_domain') != '') {
            $domain = config_item('server.cookie_domain');
        }

        if ($path === '/' && config_item('server.cookie_path') !== '/') {
            $path = config_item('server.cookie_path');
        }

        if ($secure === FALSE && config_item('server.cookie_secure') === TRUE) {
            $secure = config_item('server.cookie_secure');
        }

        if ($httponly === FALSE && config_item('server.cookie_httponly') !== FALSE) {
            $httponly = config_item('server.cookie_httponly');
        }

        if (!is_numeric($expire)) {
            $expire = time() - 86500;
        } else {
            $expire = ($expire > 0) ? time() + $expire : 0;
        }

        setcookie($prefix . $name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch the IP Address
     *
     * Determines and validates the visitor's IP address.
     *
     * @return	string	IP address
     */
    public function ip_address() {
        if ($this->ip_address !== FALSE) {
            return $this->ip_address;
        }

        $proxy_ips = config_item('server.proxy_ips');
        if (!empty($proxy_ips) && !is_array($proxy_ips)) {
            $proxy_ips = explode(',', str_replace(' ', '', $proxy_ips));
        }

        $this->ip_address = $this->server('REMOTE_ADDR');

        if ($proxy_ips) {
            foreach (array('HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_CLIENT_IP', 'HTTP_X_CLUSTER_CLIENT_IP') as $header) {
                if (($spoof = $this->server($header)) !== NULL) {
                    // Some proxies typically list the whole chain of IP
                    // addresses through which the client has reached us.
                    // e.g. client_ip, proxy_ip1, proxy_ip2, etc.
                    sscanf($spoof, '%[^,]', $spoof);

                    if (!$this->valid_ip($spoof)) {
                        $spoof = NULL;
                    } else {
                        break;
                    }
                }
            }

            if ($spoof) {
                for ($i = 0, $c = count($proxy_ips); $i < $c; $i++) {
                    // Check if we have an IP address or a subnet
                    if (strpos($proxy_ips[$i], '/') === FALSE) {
                        // An IP address (and not a subnet) is specified.
                        // We can compare right away.
                        if ($proxy_ips[$i] === $this->ip_address) {
                            $this->ip_address = $spoof;
                            break;
                        }

                        continue;
                    }

                    // We have a subnet ... now the heavy lifting begins
                    isset($separator) OR $separator = $this->valid_ip($this->ip_address, 'ipv6') ? ':' : '.';

                    // If the proxy entry doesn't match the IP protocol - skip it
                    if (strpos($proxy_ips[$i], $separator) === FALSE) {
                        continue;
                    }

                    // Convert the REMOTE_ADDR IP address to binary, if needed
                    if (!isset($ip, $sprintf)) {
                        if ($separator === ':') {
                            // Make sure we're have the "full" IPv6 format
                            $ip = explode(':', str_replace('::', str_repeat(':', 9 - substr_count($this->ip_address, ':')), $this->ip_address
                                    )
                            );

                            for ($j = 0; $j < 8; $j++) {
                                $ip[$j] = intval($ip[$j], 16);
                            }

                            $sprintf = '%016b%016b%016b%016b%016b%016b%016b%016b';
                        } else {
                            $ip = explode('.', $this->ip_address);
                            $sprintf = '%08b%08b%08b%08b';
                        }

                        $ip = vsprintf($sprintf, $ip);
                    }

                    // Split the netmask length off the network address
                    sscanf($proxy_ips[$i], '%[^/]/%d', $netaddr, $masklen);

                    // Again, an IPv6 address is most likely in a compressed form
                    if ($separator === ':') {
                        $netaddr = explode(':', str_replace('::', str_repeat(':', 9 - substr_count($netaddr, ':')), $netaddr));
                        for ($i = 0; $i < 8; $i++) {
                            $netaddr[$i] = intval($netaddr[$i], 16);
                        }
                    } else {
                        $netaddr = explode('.', $netaddr);
                    }

                    // Convert to binary and finally compare
                    if (strncmp($ip, vsprintf($sprintf, $netaddr), $masklen) === 0) {
                        $this->ip_address = $spoof;
                        break;
                    }
                }
            }
        }

        if (!$this->valid_ip($this->ip_address)) {
            return $this->ip_address = '0.0.0.0';
        }

        return $this->ip_address;
    }

    // --------------------------------------------------------------------

    /**
     * Validate IP Address
     *
     * @param	string	$ip	IP address
     * @param	string	$which	IP protocol: 'ipv4' or 'ipv6'
     * @return	bool
     */
    public function valid_ip($ip, $which = '') {
        switch (strtolower($which)) {
            case 'ipv4':
                $which = FILTER_FLAG_IPV4;
                break;
            case 'ipv6':
                $which = FILTER_FLAG_IPV6;
                break;
            default:
                $which = NULL;
                break;
        }

        return (bool) filter_var($ip, FILTER_VALIDATE_IP, $which);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch User Agent string
     *
     * @return	string|null	User Agent string or NULL if it doesn't exist
     */
    public function user_agent($xss_clean = NULL) {
        return $this->_fetch_from_array($_SERVER, 'HTTP_USER_AGENT', $xss_clean);
    }

    // --------------------------------------------------------------------

    /**
     * Sanitize Globals
     *
     * Internal method serving for the following purposes:
     *
     * 	- Unsets $_GET data, if query strings are not enabled
     * 	- Cleans POST, COOKIE and SERVER data
     * 	- Standardizes newline characters to PHP_EOL
     *
     * @return	void
     */
    protected function _sanitize_globals() {
        // Is $_GET data allowed? If not we'll set the $_GET to an empty array
        if ($this->_allow_get_array === FALSE) {
            $_GET = array();
        } elseif (is_array($_GET)) {
            foreach ($_GET as $key => $val) {
                $_GET[$this->_clean_input_keys($key)] = $this->_clean_input_data($val);
            }
        }

        // Clean $_POST Data
        if (is_array($_POST)) {
            foreach ($_POST as $key => $val) {
                $_POST[$this->_clean_input_keys($key)] = $this->_clean_input_data($val);
            }
        }

        // Clean $_COOKIE Data
        if (is_array($_COOKIE)) {
            // Also get rid of specially treated cookies that might be set by a server
            // or silly application, that are of no use to a CI application anyway
            // but that when present will trip our 'Disallowed Key Characters' alarm
            // http://www.ietf.org/rfc/rfc2109.txt
            // note that the key names below are single quoted strings, and are not PHP variables
            unset(
                    $_COOKIE['$Version'], $_COOKIE['$Path'], $_COOKIE['$Domain']
            );

            foreach ($_COOKIE as $key => $val) {
                if (($cookie_key = $this->_clean_input_keys($key)) !== FALSE) {
                    $_COOKIE[$cookie_key] = $this->_clean_input_data($val);
                } else {
                    unset($_COOKIE[$key]);
                }
            }
        }

        // Sanitize PHP_SELF
        $_SERVER['PHP_SELF'] = strip_tags($_SERVER['PHP_SELF']);
    }

    // --------------------------------------------------------------------

    /**
     * Clean Input Data
     *
     * Internal method that aids in escaping data and
     * standardizing newline characters to PHP_EOL.
     *
     * @param	string|string[]	$str	Input string(s)
     * @return	string
     */
    protected function _clean_input_data($str) {
        if (is_array($str)) {
            $new_array = array();
            foreach (array_keys($str) as $key) {
                $new_array[$this->_clean_input_keys($key)] = $this->_clean_input_data($str[$key]);
            }
            return $new_array;
        }

        /* We strip slashes if magic quotes is on to keep things consistent

          NOTE: In PHP 5.4 get_magic_quotes_gpc() will always return 0 and
          it will probably not exist in future versions at all.
         */
        if (!is_php('5.4') && get_magic_quotes_gpc()) {
            $str = stripslashes($str);
        }

        // Remove control characters
        $str = remove_invisible_characters($str, FALSE);

        // Standardize newlines if needed
        if ($this->_standardize_newlines === TRUE) {
            return preg_replace('/(?:\r\n|[\r\n])/', PHP_EOL, $str);
        }

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Clean Keys
     *
     * Internal method that helps to prevent malicious users
     * from trying to exploit keys we make sure that keys are
     * only named with alpha-numeric text and a few other items.
     *
     * @param	string	$str	Input string
     * @param	bool	$fatal	Whether to terminate script exection
     * 				or to return FALSE if an invalid
     * 				key is encountered
     * @return	string|bool
     */
    protected function _clean_input_keys($str, $fatal = TRUE) {
        if (!preg_match('/^[a-z0-9:_\/|-]+$/i', $str)) {
            if ($fatal === TRUE) {
                return FALSE;
            } else {
                set_status_header(503);
                echo 'Disallowed Key Characters.';
                exit(7); // EXIT_USER_INPUT
            }
        }
        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Request Headers
     *
     * @param	bool	$xss_clean	Whether to apply XSS filtering
     * @return	array
     */
    public function request_headers($xss_clean = FALSE) {
        // If header is already defined, return it immediately
        if (!empty($this->headers)) {
            return $this->headers;
        }

        // In Apache, you can simply call apache_request_headers()
        if (function_exists('apache_request_headers')) {
            return $this->headers = apache_request_headers();
        }

        $this->headers['Content-Type'] = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : @getenv('CONTENT_TYPE');

        foreach ($_SERVER as $key => $val) {
            if (sscanf($key, 'HTTP_%s', $header) === 1) {
                // take SOME_HEADER and turn it into Some-Header
                $header = str_replace('_', ' ', strtolower($header));
                $header = str_replace(' ', '-', ucwords($header));

                $this->headers[$header] = $this->_fetch_from_array($_SERVER, $key, $xss_clean);
            }
        }

        return $this->headers;
    }

    // --------------------------------------------------------------------

    /**
     * Get Request Header
     *
     * Returns the value of a single member of the headers class member
     *
     * @param	string		$index		Header name
     * @param	bool		$xss_clean	Whether to apply XSS filtering
     * @return	string|null	The requested header on success or NULL on failure
     */
    public function get_request_header($index, $xss_clean = FALSE) {
        static $headers;

        if (!isset($headers)) {
            empty($this->headers) && $this->request_headers();
            foreach ($this->headers as $key => $value) {
                $headers[strtolower($key)] = $value;
            }
        }

        $index = strtolower($index);

        if (!isset($headers[$index])) {
            return NULL;
        }

        return ($xss_clean === TRUE) ? $this->xss_clean($headers[$index]) : $headers[$index];
    }

    // --------------------------------------------------------------------

    /**
     * Is AJAX request?
     *
     * Test to see if a request contains the HTTP_X_REQUESTED_WITH header.
     *
     * @return 	bool
     */
    public function is_ajax_request() {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    // --------------------------------------------------------------------

    /**
     * Is CLI request?
     *
     * Test to see if a request was made from the command line.
     *
     * @deprecated	3.0.0	Use is_cli() instead
     * @return	bool
     */
    public function is_cli_request() {
        return is_cli();
    }

    // --------------------------------------------------------------------

    /**
     * Get Request Method
     *
     * Return the request method
     *
     * @param	bool	$upper	Whether to return in upper or lower case
     * 				(default: FALSE)
     * @return 	string
     */
    public function method($upper = FALSE) {
        return ($upper) ? strtoupper($this->server('REQUEST_METHOD')) : strtolower($this->server('REQUEST_METHOD'));
    }

    // ------------------------------------------------------------------------

    /**
     * Magic __get()
     *
     * Allows read access to protected properties
     *
     * @param	string	$name
     * @return	mixed
     */
    public function __get($name) {
        if ($name === 'raw_input_stream') {
            isset($this->_raw_input_stream) OR $this->_raw_input_stream = file_get_contents('php://input');
            return $this->_raw_input_stream;
        } elseif ($name === 'ip_address') {
            return $this->ip_address;
        }
    }

    /**
     * XSS Clean
     *
     * Sanitizes data so that Cross Site Scripting Hacks can be
     * prevented.  This method does a fair amount of work but
     * it is extremely thorough, designed to prevent even the
     * most obscure XSS attempts.  Nothing is ever 100% foolproof,
     * of course, but I haven't been able to get anything passed
     * the filter.
     *
     * Note: Should only be used to deal with data upon submission.
     * 	 It's not something that should be used for general
     * 	 runtime processing.
     *
     * @link	http://channel.bitflux.ch/wiki/XSS_Prevention
     * 		Based in part on some code and ideas from Bitflux.
     *
     * @link	http://ha.ckers.org/xss.html
     * 		To help develop this script I used this great list of
     * 		vulnerabilities along with a few other hacks I've
     * 		harvested from examining vulnerabilities in other programs.
     *
     * @param	string|string[]	$str		Input data
     * @param 	bool		$is_image	Whether the input is an image
     * @return	string
     */
    public function xss_clean($str, $is_image = FALSE) {
        // Is the string an array?
        if (is_array($str)) {
            while (list($key) = each($str)) {
                $str[$key] = $this->xss_clean($str[$key]);
            }

            return $str;
        }

        // Remove Invisible Characters
        $str = remove_invisible_characters($str);

        /*
         * URL Decode
         *
         * Just in case stuff like this is submitted:
         *
         * <a href="http://%77%77%77%2E%67%6F%6F%67%6C%65%2E%63%6F%6D">Google</a>
         *
         * Note: Use rawurldecode() so it does not remove plus signs
         */
        do {
            $str = rawurldecode($str);
        } while (preg_match('/%[0-9a-f]{2,}/i', $str));

        /*
         * Convert character entities to ASCII
         *
         * This permits our tests below to work reliably.
         * We only convert entities that are within tags since
         * these are the ones that will pose security problems.
         */
        $str = preg_replace_callback("/[^a-z0-9>]+[a-z0-9]+=([\'\"]).*?\\1/si", array($this, '_convert_attribute'), $str);
        $str = preg_replace_callback('/<\w+.*/si', array($this, '_decode_entity'), $str);

        // Remove Invisible Characters Again!
        $str = remove_invisible_characters($str);

        /*
         * Convert all tabs to spaces
         *
         * This prevents strings like this: ja	vascript
         * NOTE: we deal with spaces between characters later.
         * NOTE: preg_replace was found to be amazingly slow here on
         * large blocks of data, so we use str_replace.
         */
        $str = str_replace("\t", ' ', $str);

        // Capture converted string for later comparison
        $converted_string = $str;

        // Remove Strings that are never allowed
        $str = $this->_do_never_allowed($str);

        /*
         * Makes PHP tags safe
         *
         * Note: XML tags are inadvertently replaced too:
         *
         * <?xml
         *
         * But it doesn't seem to pose a problem.
         */
        if ($is_image === TRUE) {
            // Images have a tendency to have the PHP short opening and
            // closing tags every so often so we skip those and only
            // do the long opening tags.
            $str = preg_replace('/<\?(php)/i', '&lt;?\\1', $str);
        } else {
            $str = str_replace(array('<?', '?' . '>'), array('&lt;?', '?&gt;'), $str);
        }

        /*
         * Compact any exploded words
         *
         * This corrects words like:  j a v a s c r i p t
         * These words are compacted back to their correct state.
         */
        $words = array(
            'javascript', 'expression', 'vbscript', 'jscript', 'wscript',
            'vbs', 'script', 'base64', 'applet', 'alert', 'document',
            'write', 'cookie', 'window', 'confirm', 'prompt', 'eval'
        );

        foreach ($words as $word) {
            $word = implode('\s*', str_split($word)) . '\s*';

            // We only want to do this when it is followed by a non-word character
            // That way valid stuff like "dealer to" does not become "dealerto"
            $str = preg_replace_callback('#(' . substr($word, 0, -3) . ')(\W)#is', array($this, '_compact_exploded_words'), $str);
        }

        /*
         * Remove disallowed Javascript in links or img tags
         * We used to do some version comparisons and use of stripos(),
         * but it is dog slow compared to these simplified non-capturing
         * preg_match(), especially if the pattern exists in the string
         *
         * Note: It was reported that not only space characters, but all in
         * the following pattern can be parsed as separators between a tag name
         * and its attributes: [\d\s"\'`;,\/\=\(\x00\x0B\x09\x0C]
         * ... however, remove_invisible_characters() above already strips the
         * hex-encoded ones, so we'll skip them below.
         */
        do {
            $original = $str;

            if (preg_match('/<a/i', $str)) {
                $str = preg_replace_callback('#<a[^a-z0-9>]+([^>]*?)(?:>|$)#si', array($this, '_js_link_removal'), $str);
            }

            if (preg_match('/<img/i', $str)) {
                $str = preg_replace_callback('#<img[^a-z0-9]+([^>]*?)(?:\s?/?>|$)#si', array($this, '_js_img_removal'), $str);
            }

            if (preg_match('/script|xss/i', $str)) {
                $str = preg_replace('#</*(?:script|xss).*?>#si', '[removed]', $str);
            }
        } while ($original !== $str);
        unset($original);

        /*
         * Sanitize naughty HTML elements
         *
         * If a tag containing any of the words in the list
         * below is found, the tag gets converted to entities.
         *
         * So this: <blink>
         * Becomes: &lt;blink&gt;
         */
        $pattern = '#'
                . '<((?<slash>/*\s*)(?<tagName>[a-z0-9]+)(?=[^a-z0-9]|$)' // tag start and name, followed by a non-tag character
                . '[^\s\042\047a-z0-9>/=]*' // a valid attribute character immediately after the tag would count as a separator
                // optional attributes
                . '(?<attributes>(?:[\s\042\047/=]*' // non-attribute characters, excluding > (tag close) for obvious reasons
                . '[^\s\042\047>/=]+' // attribute characters
                // optional attribute-value
                . '(?:\s*=' // attribute-value separator
                . '(?:[^\s\042\047=><`]+|\s*\042[^\042]*\042|\s*\047[^\047]*\047|\s*(?U:[^\s\042\047=><`]*))' // single, double or non-quoted value
                . ')?' // end optional attribute-value group
                . ')*)' // end optional attributes group
                . '[^>]*)(?<closeTag>\>)?#isS';

        // Note: It would be nice to optimize this for speed, BUT
        //       only matching the naughty elements here results in
        //       false positives and in turn - vulnerabilities!
        do {
            $old_str = $str;
            $str = preg_replace_callback($pattern, array($this, '_sanitize_naughty_html'), $str);
        } while ($old_str !== $str);
        unset($old_str);

        /*
         * Sanitize naughty scripting elements
         *
         * Similar to above, only instead of looking for
         * tags it looks for PHP and JavaScript commands
         * that are disallowed. Rather than removing the
         * code, it simply converts the parenthesis to entities
         * rendering the code un-executable.
         *
         * For example:	eval('some code')
         * Becomes:	eval&#40;'some code'&#41;
         */
        $str = preg_replace(
                '#(alert|prompt|confirm|cmd|passthru|eval|exec|expression|system|fopen|fsockopen|file|file_get_contents|readfile|unlink)(\s*)\((.*?)\)#si', '\\1\\2&#40;\\3&#41;', $str
        );

        // Final clean up
        // This adds a bit of extra precaution in case
        // something got through the above filters
        $str = $this->_do_never_allowed($str);

        /*
         * Images are Handled in a Special Way
         * - Essentially, we want to know that after all of the character
         * conversion is done whether any unwanted, likely XSS, code was found.
         * If not, we return TRUE, as the image is clean.
         * However, if the string post-conversion does not matched the
         * string post-removal of XSS, then it fails, as there was unwanted XSS
         * code found and removed/changed during processing.
         */
        if ($is_image === TRUE) {
            return ($str === $converted_string);
        }

        return $str;
    }

    /**
     * Attribute Conversion
     *
     * @used-by	CI_Security::xss_clean()
     * @param	array	$match
     * @return	string
     */
    protected function _convert_attribute($match) {
        return str_replace(array('>', '<', '\\'), array('&gt;', '&lt;', '\\\\'), $match[0]);
    }

    /**
     * HTML Entity Decode Callback
     *
     * @used-by	CI_Security::xss_clean()
     * @param	array	$match
     * @return	string
     */
    protected function _decode_entity($match) {
        // Protect GET variables in URLs
        // 901119URL5918AMP18930PROTECT8198
        $match = preg_replace('|\&([a-z\_0-9\-]+)\=([a-z\_0-9\-/]+)|i', $this->xss_hash() . '\\1=\\2', $match[0]);

        // Decode, then un-protect URL GET vars
        return str_replace(
                $this->xss_hash(), '&', $this->entity_decode($match, $this->charset)
        );
    }

    /**
     * XSS Hash
     *
     * Generates the XSS hash if needed and returns it.
     *
     * @see		CI_Security::$_xss_hash
     * @return	string	XSS hash
     */
    public function xss_hash() {
        if ($this->_xss_hash === NULL) {
            $rand = $this->get_random_bytes(16);
            $this->_xss_hash = ($rand === FALSE) ? md5(uniqid(mt_rand(), TRUE)) : bin2hex($rand);
        }

        return $this->_xss_hash;
    }

    /**
     * Get random bytes
     *
     * @param	int	$length	Output length
     * @return	string
     */
    public function get_random_bytes($length) {
        if (empty($length) OR ! ctype_digit((string) $length)) {
            return FALSE;
        }

        if (function_exists('random_bytes')) {
            try {
                // The cast is required to avoid TypeError
                return random_bytes((int) $length);
            } catch (Exception $e) {
                // If random_bytes() can't do the job, we can't either ...
                // There's no point in using fallbacks.
                logMessage($e->getMessage(), LOG_ERR);
                return FALSE;
            }
        }

        // Unfortunately, none of the following PRNGs is guaranteed to exist ...
        if (defined('MCRYPT_DEV_URANDOM') && ($output = mcrypt_create_iv($length, MCRYPT_DEV_URANDOM)) !== FALSE) {
            return $output;
        }


        if (is_readable('/dev/urandom') && ($fp = fopen('/dev/urandom', 'rb')) !== FALSE) {
            // Try not to waste entropy ...
            is_php('5.4') && stream_set_chunk_size($fp, $length);
            $output = fread($fp, $length);
            fclose($fp);
            if ($output !== FALSE) {
                return $output;
            }
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            return openssl_random_pseudo_bytes($length);
        }

        return FALSE;
    }

    /**
     * Do Never Allowed
     *
     * @used-by	CI_Security::xss_clean()
     * @param 	string
     * @return 	string
     */
    protected function _do_never_allowed($str) {
        $str = str_replace(array_keys($this->_never_allowed_str), $this->_never_allowed_str, $str);

        foreach ($this->_never_allowed_regex as $regex) {
            $str = preg_replace('#' . $regex . '#is', '[removed]', $str);
        }

        return $str;
    }

    /**
     * Compact Exploded Words
     *
     * Callback method for xss_clean() to remove whitespace from
     * things like 'j a v a s c r i p t'.
     *
     * @used-by	CI_Security::xss_clean()
     * @param	array	$matches
     * @return	string
     */
    protected function _compact_exploded_words($matches) {
        return preg_replace('/\s+/s', '', $matches[1]) . $matches[2];
    }

    protected function _js_link_removal($match) {
        return str_replace(
                $match[1], preg_replace(
                        '#href=.*?(?:(?:alert|prompt|confirm)(?:\(|&\#40;)|javascript:|livescript:|mocha:|charset=|window\.|document\.|\.cookie|<script|<xss|data\s*:)#si', '', $this->_filter_attributes($match[1])
                ), $match[0]
        );
    }

    /**
     * JS Image Removal
     *
     * Callback method for xss_clean() to sanitize image tags.
     *
     * This limits the PCRE backtracks, making it more performance friendly
     * and prevents PREG_BACKTRACK_LIMIT_ERROR from being triggered in
     * PHP 5.2+ on image tag heavy strings.
     *
     * @used-by	CI_Security::xss_clean()
     * @param	array	$match
     * @return	string
     */
    protected function _js_img_removal($match) {
        return str_replace(
                $match[1], preg_replace(
                        '#src=.*?(?:(?:alert|prompt|confirm|eval)(?:\(|&\#40;)|javascript:|livescript:|mocha:|charset=|window\.|document\.|\.cookie|<script|<xss|base64\s*,)#si', '', $this->_filter_attributes($match[1])
                ), $match[0]
        );
    }

    /**
     * Filter Attributes
     *
     * Filters tag attributes for consistency and safety.
     *
     * @used-by	CI_Security::_js_img_removal()
     * @used-by	CI_Security::_js_link_removal()
     * @param	string	$str
     * @return	string
     */
    protected function _filter_attributes($str) {
        $out = '';
        if (preg_match_all('#\s*[a-z\-]+\s*=\s*(\042|\047)([^\\1]*?)\\1#is', $str, $matches)) {
            foreach ($matches[0] as $match) {
                $out .= preg_replace('#/\*.*?\*/#s', '', $match);
            }
        }

        return $out;
    }

    /**
     * Sanitize Naughty HTML
     *
     * Callback method for xss_clean() to remove naughty HTML elements.
     *
     * @used-by	CI_Security::xss_clean()
     * @param	array	$matches
     * @return	string
     */
    protected function _sanitize_naughty_html($matches) {
        static $naughty_tags = array(
            'alert', 'prompt', 'confirm', 'applet', 'audio', 'basefont', 'base', 'behavior', 'bgsound',
            'blink', 'body', 'embed', 'expression', 'form', 'frameset', 'frame', 'head', 'html', 'ilayer',
            'iframe', 'input', 'button', 'select', 'isindex', 'layer', 'link', 'meta', 'keygen', 'object',
            'plaintext', 'style', 'script', 'textarea', 'title', 'math', 'video', 'svg', 'xml', 'xss'
        );

        static $evil_attributes = array(
            'on\w+', 'style', 'xmlns', 'formaction', 'form', 'xlink:href', 'FSCommand', 'seekSegmentTime'
        );

        // First, escape unclosed tags
        if (empty($matches['closeTag'])) {
            return '&lt;' . $matches[1];
        }
        // Is the element that we caught naughty? If so, escape it
        elseif (in_array(strtolower($matches['tagName']), $naughty_tags, TRUE)) {
            return '&lt;' . $matches[1] . '&gt;';
        }
        // For other tags, see if their attributes are "evil" and strip those
        elseif (isset($matches['attributes'])) {
            // We'll store the already fitlered attributes here
            $attributes = array();

            // Attribute-catching pattern
            $attributes_pattern = '#'
                    . '(?<name>[^\s\042\047>/=]+)' // attribute characters
                    // optional attribute-value
                    . '(?:\s*=(?<value>[^\s\042\047=><`]+|\s*\042[^\042]*\042|\s*\047[^\047]*\047|\s*(?U:[^\s\042\047=><`]*)))' // attribute-value separator
                    . '#i';

            // Blacklist pattern for evil attribute names
            $is_evil_pattern = '#^(' . implode('|', $evil_attributes) . ')$#i';

            // Each iteration filters a single attribute
            do {
                // Strip any non-alpha characters that may preceed an attribute.
                // Browsers often parse these incorrectly and that has been a
                // of numerous XSS issues we've had.
                $matches['attributes'] = preg_replace('#^[^a-z]+#i', '', $matches['attributes']);

                if (!preg_match($attributes_pattern, $matches['attributes'], $attribute, PREG_OFFSET_CAPTURE)) {
                    // No (valid) attribute found? Discard everything else inside the tag
                    break;
                }

                if (
                // Is it indeed an "evil" attribute?
                        preg_match($is_evil_pattern, $attribute['name'][0])
                        // Or does it have an equals sign, but no value and not quoted? Strip that too!
                        OR ( trim($attribute['value'][0]) === '')
                ) {
                    $attributes[] = 'xss=removed';
                } else {
                    $attributes[] = $attribute[0][0];
                }

                $matches['attributes'] = substr($matches['attributes'], $attribute[0][1] + strlen($attribute[0][0]));
            } while ($matches['attributes'] !== '');

            $attributes = empty($attributes) ? '' : ' ' . implode(' ', $attributes);
            return '<' . $matches['slash'] . $matches['tagName'] . $attributes . '>';
        }

        return $matches[0];
    }

    /**
     * HTML Entities Decode
     *
     * A replacement for html_entity_decode()
     *
     * The reason we are not using html_entity_decode() by itself is because
     * while it is not technically correct to leave out the semicolon
     * at the end of an entity most browsers will still interpret the entity
     * correctly. html_entity_decode() does not convert entities without
     * semicolons, so we are left with our own little solution here. Bummer.
     *
     * @link	http://php.net/html-entity-decode
     *
     * @param	string	$str		Input
     * @param	string	$charset	Character set
     * @return	string
     */
    public function entity_decode($str, $charset = NULL) {
        if (strpos($str, '&') === FALSE) {
            return $str;
        }

        static $_entities;

        isset($charset) OR $charset = $this->charset;
        $flag = is_php('5.4') ? ENT_COMPAT | ENT_HTML5 : ENT_COMPAT;

        do {
            $str_compare = $str;

            // Decode standard entities, avoiding false positives
            if (preg_match_all('/&[a-z]{2,}(?![a-z;])/i', $str, $matches)) {
                if (!isset($_entities)) {
                    $_entities = array_map(
                            'strtolower', is_php('5.3.4') ? get_html_translation_table(HTML_ENTITIES, $flag, $charset) : get_html_translation_table(HTML_ENTITIES, $flag)
                    );

                    // If we're not on PHP 5.4+, add the possibly dangerous HTML 5
                    // entities to the array manually
                    if ($flag === ENT_COMPAT) {
                        $_entities[':'] = '&colon;';
                        $_entities['('] = '&lpar;';
                        $_entities[')'] = '&rpar;';
                        $_entities["\n"] = '&newline;';
                        $_entities["\t"] = '&tab;';
                    }
                }

                $replace = array();
                $matches = array_unique(array_map('strtolower', $matches[0]));
                foreach ($matches as &$match) {
                    if (($char = array_search($match . ';', $_entities, TRUE)) !== FALSE) {
                        $replace[$match] = $char;
                    }
                }

                $str = str_ireplace(array_keys($replace), array_values($replace), $str);
            }

            // Decode numeric & UTF16 two byte entities
            $str = html_entity_decode(
                    preg_replace('/(&#(?:x0*[0-9a-f]{2,5}(?![0-9a-f;])|(?:0*\d{2,4}(?![0-9;]))))/iS', '$1;', $str), $flag, $charset
            );
        } while ($str_compare !== $str);
        return $str;
    }

}

//======================================================================
//设置响应状态码
//======================================================================
if (!function_exists('set_status_header')) {

    /**
     * Set HTTP Status Header
     *
     * @param	int	the status code
     * @param	string
     * @return	void
     */
    function set_status_header($code = 200, $text = '') {
        if (is_cli()) {
            return;
        }

        if (empty($code) OR ! is_numeric($code)) {
            show_error('Status codes must be numeric', 500);
        }

        if (empty($text)) {
            is_int($code) OR $code = (int) $code;
            $stati = array(
                100 => 'Continue',
                101 => 'Switching Protocols',
                200 => 'OK',
                201 => 'Created',
                202 => 'Accepted',
                203 => 'Non-Authoritative Information',
                204 => 'No Content',
                205 => 'Reset Content',
                206 => 'Partial Content',
                300 => 'Multiple Choices',
                301 => 'Moved Permanently',
                302 => 'Found',
                303 => 'See Other',
                304 => 'Not Modified',
                305 => 'Use Proxy',
                307 => 'Temporary Redirect',
                400 => 'Bad Request',
                401 => 'Unauthorized',
                402 => 'Payment Required',
                403 => 'Forbidden',
                404 => 'Not Found',
                405 => 'Method Not Allowed',
                406 => 'Not Acceptable',
                407 => 'Proxy Authentication Required',
                408 => 'Request Timeout',
                409 => 'Conflict',
                410 => 'Gone',
                411 => 'Length Required',
                412 => 'Precondition Failed',
                413 => 'Request Entity Too Large',
                414 => 'Request-URI Too Long',
                415 => 'Unsupported Media Type',
                416 => 'Requested Range Not Satisfiable',
                417 => 'Expectation Failed',
                422 => 'Unprocessable Entity',
                500 => 'Internal Server Error',
                501 => 'Not Implemented',
                502 => 'Bad Gateway',
                503 => 'Service Unavailable',
                504 => 'Gateway Timeout',
                505 => 'HTTP Version Not Supported'
            );

            if (isset($stati[$code])) {
                $text = $stati[$code];
            } else {
                logMessage('No status text available. Please check your status code number or supply your own message text.', LOG_INFO);
            }
        }

        if (strpos(PHP_SAPI, 'cgi') === 0) {
            header('Status: ' . $code . ' ' . $text, TRUE);
        } else {
            $server_protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
            header($server_protocol . ' ' . $code . ' ' . $text, TRUE, $code);
        }
    }

}

//======================================================================
//字符串相关函数
//======================================================================
if (!function_exists('remove_invisible_characters')) {

    /**
     * Remove Invisible Characters
     *
     * This prevents sandwiching null characters
     * between ascii characters, like Java\0script.
     *
     * @param	string
     * @param	bool
     * @return	string
     */
    function remove_invisible_characters($str, $url_encoded = TRUE) {
        $non_displayables = array();

        // every control character except newline (dec 10),
        // carriage return (dec 13) and horizontal tab (dec 09)
        if ($url_encoded) {
            $non_displayables[] = '/%0[0-8bcef]/'; // url encoded 00-08, 11, 12, 14, 15
            $non_displayables[] = '/%1[0-9a-f]/'; // url encoded 16-31
        }

        $non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S'; // 00-08, 11, 12, 14-31, 127

        do {
            $str = preg_replace($non_displayables, '', $str, -1, $count);
        } while ($count);

        return $str;
    }

}

if (!function_exists('is_php')) {

    /**
     * Determines if the current version of PHP is equal to or greater than the supplied value
     *
     * @param	string
     * @return	bool	TRUE if the current version is $version or higher
     */
    function is_php($version) {
        static $_is_php;
        $version = (string) $version;

        if (!isset($_is_php[$version])) {
            $_is_php[$version] = version_compare(PHP_VERSION, $version, '>=');
        }

        return $_is_php[$version];
    }

}
