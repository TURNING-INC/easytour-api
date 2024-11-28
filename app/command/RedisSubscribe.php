<?php

namespace app\command;
use app\lib\Tools;
use app\service\front\OrdersService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

ini_set('default_socket_timeout', -1);  //设置不超时

class redisSubscribe extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('redis:ex:subscribe')
            ->setDescription('回收订单');
    }

    protected function execute(Input $input, Output $output)
    {
        // 指令输出
        $output->writeln('回收订单');

        try {
            $redis = Tools::redis();
            // 解决Redis客户端订阅时候超时情况
            //$redis->setOption(\Redis::OPT_NOTIFY_KEYSPACE_EVENTS, 'KEA');

            $redis->psubscribe(array('__keyevent@0__:expired'), function ($redis, $pattern, $chan, $msg)
            {
                $this->output->writeln('回收订单key:' . $msg);
                try {
                    $detail = app(OrdersService::class)->cancel($msg, true);
                    $this->output->writeln("处理细节：" . implode('；', $detail));
                } catch (\Exception $e) {
                    $this->output->writeln("回收订单{$msg}失败：{$e->getMessage()}");
                }
            });

        } catch (\Exception $e) {
            $output->writeln($e->getFile() . '：' . $e->getLine());
            $output->writeln($e->getMessage());
        }
    }
}