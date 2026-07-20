<?php

declare(strict_types=1);

namespace Swoole\FastCGI;


class HttpRequest {

    
    public function getScheme(): \string {
        return "";
    }

    
    public function withScheme(\string $scheme): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withoutScheme() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getMethod(): \string {
        return "";
    }

    
    public function withMethod(\string $method): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withoutMethod() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getDocumentRoot(): \string {
        return "";
    }

    
    public function withDocumentRoot(\string $documentRoot): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withoutDocumentRoot() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getScriptFilename(): \string {
        return "";
    }

    
    public function withScriptFilename(\string $scriptFilename): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withoutScriptFilename() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getScriptName(): \string {
        return "";
    }

    
    public function withScriptName(\string $scriptName): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withoutScriptName() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function withUri(\string $uri): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function getDocumentUri(): \string {
        return "";
    }

    
    public function withDocumentUri(\string $documentUri): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withoutDocumentUri() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getRequestUri(): \string {
        return "";
    }

    
    public function withRequestUri(\string $requestUri): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withoutRequestUri() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function withQuery($query): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function getQueryString(): \string {
        return "";
    }

    
    public function withQueryString(\string $queryString): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withoutQueryString() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getContentType(): \string {
        return "";
    }

    
    public function withContentType(\string $contentType): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withoutContentType() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getContentLength(): \int {
        return 0;
    }

    
    public function withContentLength(\int $contentLength): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withoutContentLength() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getGatewayInterface(): \string {
        return "";
    }

    
    public function withGatewayInterface(\string $gatewayInterface): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withoutGatewayInterface() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getServerProtocol(): \string {
        return "";
    }

    
    public function withServerProtocol(\string $serverProtocol): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withoutServerProtocol() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function withProtocolVersion(\string $protocolVersion): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function getServerSoftware(): \string {
        return "";
    }

    
    public function withServerSoftware(\string $serverSoftware): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withoutServerSoftware() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getRemoteAddr(): \string {
        return "";
    }

    
    public function withRemoteAddr(\string $remoteAddr): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withoutRemoteAddr() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getRemotePort(): \int {
        return 0;
    }

    
    public function withRemotePort(\int $remotePort): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withoutRemotePort() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getServerAddr(): \string {
        return "";
    }

    
    public function withServerAddr(\string $serverAddr): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withoutServerAddr() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getServerPort(): \int {
        return 0;
    }

    
    public function withServerPort(\int $serverPort): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withoutServerPort() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getServerName(): \string {
        return "";
    }

    
    public function withServerName(\string $serverName): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withoutServerName() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getRedirectStatus(): \string {
        return "";
    }

    
    public function withRedirectStatus(\string $redirectStatus): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withoutRedirectStatus() {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getHeader(\string $name): \string {
        return "";
    }

    
    public function withHeader(\string $name, \string $value): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withoutHeader(\string $name) {
        return class_exists(\mixed::class) ? \mixed::class : \stdClass::class;
    }

    
    public function getHeaders(): \array {
        return [];
    }

    
    public function withHeaders(\array $headers): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }

    
    public function withBody(\Stringable|array|string $body): \Swoole\FastCGI\HttpRequest {
        return class_exists(\Swoole\FastCGI\HttpRequest::class) ? \Swoole\FastCGI\HttpRequest::class : \stdClass::class;
    }
}
