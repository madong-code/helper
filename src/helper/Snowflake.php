<?php
/**
 *+------------------
 * madong
 *+------------------
 * Copyright (c) https://gitee.com/motion-code  All rights reserved.
 *+------------------
 * Author: Mr. April (405784684@qq.com)
 *+------------------
 * Official Website: http://www.madong.tech
 */

namespace madong\helper;


/**
 * 雪花ID生成
 *
 * @author Mr.April
 * @since  1.0
 */
class Snowflake
{
    /**
     * 起始时间戳 (2025-01-01 00:00:00 UTC)
     */
    private const START_EPOCH = 1704038400000;

    /**
     * @var int 数据中心节点ID
     */
    private int $nodeId;

    /**
     * @var int 进程ID
     */
    private int $workerId;

    /**
     * @var int 当前毫秒内的序列号
     */
    private int $currentSequence = 0;

    /**
     * @var int 上一次生成ID的时间戳(毫秒)
     */
    private int $lastTimestampMs = -1;

    /**
     * @var int 最大数据中心节点ID
     */
    private int $maxNodeId;

    /**
     * @var int 最大进程ID
     */
    private int $maxWorkerId;

    /**
     * @var int 最大序列号值
     */
    private int $maxSequenceValue;

    /**
     * @var int 进程ID的位移量
     */
    private int $workerIdBitShift;

    /**
     * @var int 节点ID的位移量
     */
    private int $nodeIdBitShift;

    /**
     * @var int 时间戳的位移量
     */
    private int $timestampBitShift;

    /**
     * 初始化ID生成器
     *
     * @param int   $nodeId   数据中心节点ID
     * @param int   $workerId 进程ID
     * @param array $config   配置项 [
     *                        'node_id_bits'   => 3,  // 节点ID位数
     *                        'worker_id_bits' => 7,  // 进程ID位数
     *                        'sequence_bits'  => 12  // 序列号位数
     *                        ]
     *
     * @throws InvalidArgumentException 当配置无效时抛出
     */
    public function __construct(
        int   $nodeId = 0,
        int   $workerId = 0,
        array $config = [
            'node_id_bits'   => 3,
            'worker_id_bits' => 7,
            'sequence_bits'  => 12,
        ]
    )
    {
        $this->validateConfig($config);
        $this->initializeComponents($nodeId, $workerId, $config);
    }

    /**
     * 验证配置有效性
     */
    private function validateConfig(array $config): void
    {
        $totalBits = $config['node_id_bits'] + $config['worker_id_bits'] + $config['sequence_bits'];

        if ($totalBits > 22) {
            throw new InvalidArgumentException(
                "配置错误：总位数不能超过22位。当前配置: $totalBits 位"
            );
        }
    }

    /**
     * 初始化生成器组件
     */
    private function initializeComponents(int $nodeId, int $workerId, array $config): void
    {
        // 计算最大值
        $this->maxNodeId        = (1 << $config['node_id_bits']) - 1;
        $this->maxWorkerId      = (1 << $config['worker_id_bits']) - 1;
        $this->maxSequenceValue = (1 << $config['sequence_bits']) - 1;

        // 计算位移量
        $this->workerIdBitShift  = $config['sequence_bits'];
        $this->nodeIdBitShift    = $config['sequence_bits'] + $config['worker_id_bits'];
        $this->timestampBitShift = $config['sequence_bits'] + $config['worker_id_bits'] + $config['node_id_bits'];

        // 验证ID范围
        if ($nodeId < 0 || $nodeId > $this->maxNodeId) {
            throw new InvalidArgumentException(
                "节点ID范围必须在 0 到 {$this->maxNodeId} 之间"
            );
        }

        if ($workerId < 0 || $workerId > $this->maxWorkerId) {
            throw new InvalidArgumentException(
                "进程ID范围必须在 0 到 {$this->maxWorkerId} 之间"
            );
        }

        $this->nodeId   = $nodeId;
        $this->workerId = $workerId;
    }

    /**
     * 生成唯一ID
     *
     * @throws RuntimeException 当时钟回拨时抛出
     */
    public function generate(): int
    {
        $currentTimestampMs = $this->getTimestampMs();

        $this->validateTimeSequence($currentTimestampMs);

        // 同一毫秒内的序列处理
        if ($currentTimestampMs === $this->lastTimestampMs) {
            $this->currentSequence = ($this->currentSequence + 1) & $this->maxSequenceValue;
            if ($this->currentSequence === 0) {
                $currentTimestampMs = $this->waitNextMillisecond($this->lastTimestampMs);
            }
        } else {
            $this->currentSequence = 0;
        }

        $this->lastTimestampMs = $currentTimestampMs;

        return $this->constructId($currentTimestampMs);
    }

    /**
     * 验证时间序列有效性
     */
    private function validateTimeSequence(int $currentTimestampMs): void
    {
        if ($currentTimestampMs < $this->lastTimestampMs) {
            throw new RuntimeException("检测到时钟回拨，拒绝生成ID");
        }
    }

    /**
     * 组装各部分生成最终ID
     */
    private function constructId(int $currentTimestampMs): int
    {
        $elapsedTimeMs = $currentTimestampMs - self::START_EPOCH;

        return ($elapsedTimeMs << $this->timestampBitShift)
            | ($this->nodeId << $this->nodeIdBitShift)
            | ($this->workerId << $this->workerIdBitShift)
            | $this->currentSequence;
    }

    /**
     * 获取当前毫秒时间戳
     */
    private function getTimestampMs(): int
    {
        return (int)(microtime(true) * 1000);
    }

    /**
     * 等待直到下一毫秒
     */
    private function waitNextMillisecond(int $lastMs): int
    {
        $currentMs = $this->getTimestampMs();

        while ($currentMs <= $lastMs) {
            usleep(100);
            $currentMs = $this->getTimestampMs();
        }

        return $currentMs;
    }

    /**
     * 解析雪花ID
     */
    public function parse(int $snowflakeId): array
    {
        $timestampMs = ($snowflakeId >> $this->timestampBitShift) + self::START_EPOCH;
        $nodeId      = ($snowflakeId >> $this->nodeIdBitShift) & $this->maxNodeId;
        $workerId    = ($snowflakeId >> $this->workerIdBitShift) & $this->maxWorkerId;
        $sequence    = $snowflakeId & $this->maxSequenceValue;

        return [
            'timestamp_ms' => $timestampMs,
            'node_id'      => $nodeId,
            'worker_id'    => $workerId,
            'sequence'     => $sequence,
        ];
    }
}