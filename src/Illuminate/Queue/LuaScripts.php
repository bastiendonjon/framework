<?php

namespace Illuminate\Queue;

class LuaScripts
{
    /**
     * Get the Lua script for popping the next job off of the queue.
     *
     * @return string
     */
    public static function pop()
    {
        return <<<'LUA'
local job = redis.call('lpop', KEYS[1])
local reserved = false
if(job ~= false) then
    reserved = cjson.decode(job)
    reserved['attempts'] = reserved['attempts'] + 1
    reserved = cjson.encode(reserved)
    redis.call('zadd', KEYS[2], KEYS[3], reserved)
end
return {job, reserved}
LUA;
    }

    /**
     * Get the Lua script for releasing reserved jobs.
     *
     * @return string
     */
    public static function release()
    {
        return <<<'LUA'
redis.call('zrem', KEYS[2], KEYS[3])
redis.call('zadd', KEYS[1], KEYS[4], KEYS[3])
return true
LUA;
    }

    /**
     * Get the Lua script to migrate expired jobs back onto the queue.
     *
     * @return string
     */
    public static function migrateExpiredJobs()
    {
        return <<<'LUA'
local val = redis.call('zrangebyscore', KEYS[1], '-inf', KEYS[3])
if(next(val) ~= nil) then
    redis.call('zremrangebyrank', KEYS[1], 0, #val - 1)
    for i = 1, #val, 100 do
        redis.call('rpush', KEYS[2], unpack(val, i, math.min(i+99, #val)))
    end
end
return true
LUA;
    }
}
