<?php

/**
 * Authentication tokens.
 * <p>
 * Any strings added to the list will allow using of the API.
 */
define("AUTH_TOKENS", []);

if (version_compare(phpversion(), '7.1', '>=')) {
    ini_set('serialize_precision', -1);
}

/**
 * Write a response.
 * 
 * @param array $data Response contents.
 * @param int $http_code HTTP response code. Default: 200.
 *
 * @return void
 */
function write_response($data, $http_code = 200) {
    header('Content-Type: application/json;charset=utf-8');
    http_response_code($http_code);
    echo(json_encode($data));
    exit();
}

/**
 * Write an error response.
 * 
 * @param string $message Error message
 * @param int $http_code HTTP response code. Default: 500.
 *
 * @return void
 */
function write_error($message, $http_code = 500) {
    write_response([
        'error' => $message
    ], $http_code);
}

/**
 * Check whether a $needle is the ending part of a $haystack.
 * 
 * @param string $haystack Text contents.
 * @param string $needle String to check at the end of the $haystack.
 *
 * @return boolean True if ends with, otherwise false.
 */
function ends_with($haystack, $needle) {
    return substr($haystack, strlen($haystack) - strlen($needle)) === $needle;
}

/**
 * Authenticate the request considering the defined AUTH_TOKENS.
 *
 * @return void
 */
function authenticate_request() {
    if (empty(AUTH_TOKENS)) {
        return;
    }

    $request_token = $_REQUEST['token'];

    if (empty($request_token) || !in_array($request_token, AUTH_TOKENS)) {
        write_error('unauthorized', 401);
    }
}

/**
 * Validate the request data to ensure all data required is available.
 *
 * @return void
 */
function validate_request() {
    if (empty($_REQUEST['stock'])) {
        write_error('no stock code', 400);
    }
}

/**
 * Parse a stock code, removing any fragment related text.
 *
 * @return string Parsed stock code.
 */
function get_stock_code() {
    $possible_code = $_REQUEST['stock'];

    if (ends_with($possible_code, 'F')) {
        return substr($possible_code, 0, strlen($possible_code) - 1);
    } else {
        return $possible_code;
    }
}

/**
 * Get the page that contains the stock fundamental details.
 * 
 * @param string $stock_code Stock code.
 *
 * @return string HTML code of the page.
 */
function get_stock_page($stock_code) {
    $curl_handle = curl_init();
    $url = 'http://www.fundamentus.com.br/detalhes.php?papel=' . $stock_code;

    curl_setopt($curl_handle, CURLOPT_URL, $url);
    curl_setopt($curl_handle, CURLOPT_ENCODING, 'identity');
    curl_setopt($curl_handle, CURLOPT_HEADER , true); 
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_handle, CURLOPT_HTTPHEADER, array(
        'User-agent: Mozilla/5.0 (Windows; U; Windows NT 6.1; rv:2.2) Gecko/20110201',
        'Accept: text/html, text/plain, text/css, text/sgml, */*;q=0.01'
    ));
    
    $html = curl_exec($curl_handle);
    $http_code = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
    curl_close($curl_handle);

    if ($http_code == 200) {
        $html = mb_convert_encoding($html, 'ISO-8859-1', 'utf-8');
        return str_replace(array("\r", "\n"), '', $html);
    } else {
        write_error('could not get stock data');
    }

    exit();
}

/**
 * Get stock data, calculating its intrinsic value using the Benjamin Graham’s Formula.
 * 
 * @param string $stock_code Stock code to look for data.
 *
 * @return array Stock data.
 */
function get_stock_data($stock_code) {
    $html = get_stock_page($stock_code);

    if (strpos($html, "Nenhum papel encontrado")) {
        write_error('no stock found', 404);
    }

    preg_match_all('/<span\sclass="txt"[^>]*>((?:.|\n)*?)<\/span>/', $html, $matches, PREG_SET_ORDER);

    $required_keys = [
        'Cota??o' => 'price',
        'LPA' => 'eps', // Earnings Per Share (EPS) (Value)
        'VPA' => 'bvps', // Book Value Per Share (BVP) (Value)
        'ROE' => 'roe', // Return on Equity (ROE) (%)
        'P/L' => 'pe', // Price/Earnings (P/E) (Years)
        'P/VP' => 'pbv', // Price/Book Value Ratio (P/BV) (Ratio)
        'Div. Yield' => 'dy' // Divident Yield (%)
    ];

    $data = [];
    
    for ($index = 0; $index < sizeof($matches); $index++) {
        $possible_key = $matches[$index][1];

        if (array_key_exists($possible_key, $required_keys)) {
            $value = preg_replace('/\s|%/', '', $matches[++$index][1]);
            $data[$required_keys[$possible_key]] = floatval(str_replace(',', '.', $value));
        }
    }

    return $data;
}

/**
 * Write stock data.
 *
 * @return void
 */
function write_stock_data() {
    $stock_code = get_stock_code();
    $stock_data = get_stock_data($stock_code);

    $eps = $stock_data['eps'];

    $intrinsic_value = 0.0;
    if (is_numeric($stock_data['bvps']) && $eps > 0 && $stock_data['bvps'] > 0) {
        $intrinsic_value = round(sqrt(22.5 * $eps * $stock_data['bvps']), 2);
    }

    $safety_margin = 0.0;
    if ($intrinsic_value > 0 && is_numeric($stock_data['price'])) {
        $safety_margin = round(-(($stock_data['price'] - $intrinsic_value) / $intrinsic_value), 4) * 100;
    }

    write_response([
        'stock' => $stock_code,
        'price' => get_param_value($stock_data['price']),
        'iv' => $intrinsic_value,
        'sm' => $safety_margin,
        'eps' => get_param_value($eps),
        'bvps' => get_param_value($stock_data['bvps']),
        'roe' => get_param_value($stock_data['roe']),
        'pe' => get_param_value($stock_data['pe']),
        'pbv' => get_param_value($stock_data['pbv']),
        'dy' => get_param_value($stock_data['dy'])
    ]);
}

/**
 * Get a param value if it's not null.
 *
 * @return float
 */
function get_param_value($value) {
    return $value == null ? 0.0 : $value;
}

authenticate_request();
validate_request();
write_stock_data();
?>