<?php
namespace app;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Request;
use think\Response;

/**
 * 应用异常处理类
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）
     *
     * @access public
     * @param  \Throwable $exception
     * @return void
     */
    public function report(\Throwable $exception): void
    {
        // 使用内置的方式记录异常日志
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @access public
     * @param \think\Request   $request
     * @param \Throwable $e
     * @return Response
     */
    public function render($request, \Throwable $e): Response
    {
        if (!$e instanceof HttpResponseException && $this->isJson($request)) {
            return $this->renderJsonException($e);
        }

        // 其他错误交给系统处理
        return parent::render($request, $e);
    }

    /**
     * API、Ajax、Accept JSON 请求在调试模式下也强制返回 JSON。
     */
    protected function isJson(Request $request): bool
    {
        $path = trim($request->pathinfo(), '/');
        $accept = strtolower((string) $request->header('accept', ''));
        $contentType = strtolower((string) $request->header('content-type', ''));

        return str_starts_with($path, 'api/')
            || parent::isJson($request)
            || $request->isAjax(true)
            || str_contains($accept, 'application/json')
            || str_contains($accept, '+json')
            || str_contains($contentType, 'application/json')
            || str_contains($contentType, '+json');
    }

    /**
     * 渲染JSON异常输出
     * @param \Throwable $e
     * @return Response\Json
     */
    private function renderJsonException(\Throwable $e): Response
    {
        $status = $e instanceof HttpException ? $e->getStatusCode() : 500;
        $code = $this->getCode($e) ?: $status;
        $data = null;

        if (app()->isDebug()) {
            $data = [
                'debug' => [
                    'exception' => $e::class,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ],
            ];
        }

        $response = json([
            'code' => $code,
            'message' => $this->getMessage($e),
            'data' => $data,
        ], $status);

        if ($e instanceof HttpException) {
            $response->header($e->getHeaders());
        }

        return $response;
    }
}
