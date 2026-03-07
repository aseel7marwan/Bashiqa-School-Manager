<?php
/**
 * نظام التخزين المؤقت - Simple Cache System
 * يحسّن الأداء عبر تخزين الاستعلامات المتكررة
 * 
 * @package SchoolManager
 */

class SimpleCache {
    private static $memoryCache = [];
    private static $sessionKey = '_query_cache';
    
    /**
     * الحصول على قيمة من الكاش
     * @param string $key المفتاح
     * @param string $type نوع الكاش (memory, session)
     * @return mixed|null
     */
    public static function get($key, $type = 'memory') {
        if ($type === 'session') {
            return $_SESSION[self::$sessionKey][$key] ?? null;
        }
        return self::$memoryCache[$key] ?? null;
    }
    
    /**
     * تخزين قيمة في الكاش
     * @param string $key المفتاح
     * @param mixed $value القيمة
     * @param string $type نوع الكاش (memory, session)
     * @param int $ttl وقت الصلاحية بالثواني (للجلسة فقط)
     */
    public static function set($key, $value, $type = 'memory', $ttl = 300) {
        if ($type === 'session') {
            $_SESSION[self::$sessionKey][$key] = [
                'value' => $value,
                'expires' => time() + $ttl
            ];
        } else {
            self::$memoryCache[$key] = $value;
        }
    }
    
    /**
     * حذف قيمة من الكاش
     * @param string $key المفتاح (أو null لحذف الكل)
     * @param string $type نوع الكاش
     */
    public static function delete($key = null, $type = 'memory') {
        if ($key === null) {
            if ($type === 'session') {
                unset($_SESSION[self::$sessionKey]);
            } else {
                self::$memoryCache = [];
            }
        } else {
            if ($type === 'session') {
                unset($_SESSION[self::$sessionKey][$key]);
            } else {
                unset(self::$memoryCache[$key]);
            }
        }
    }
    
    /**
     * الحصول أو التخزين (ذكي)
     * @param string $key المفتاح
     * @param callable $callback دالة لجلب البيانات
     * @param string $type نوع الكاش
     * @param int $ttl وقت الصلاحية
     * @return mixed
     */
    public static function remember($key, $callback, $type = 'memory', $ttl = 300) {
        // تحقق من الكاش
        $cached = self::get($key, $type);
        
        if ($type === 'session' && $cached !== null) {
            // تحقق من انتهاء الصلاحية
            if (isset($cached['expires']) && $cached['expires'] > time()) {
                return $cached['value'];
            }
            self::delete($key, $type);
        } elseif ($type === 'memory' && $cached !== null) {
            return $cached;
        }
        
        // جلب البيانات وتخزينها
        $value = $callback();
        self::set($key, $value, $type, $ttl);
        
        return $value;
    }
    
    /**
     * تنظيف الكاش المنتهي
     */
    public static function cleanup() {
        if (!isset($_SESSION[self::$sessionKey])) return;
        
        $now = time();
        foreach ($_SESSION[self::$sessionKey] as $key => $data) {
            if (isset($data['expires']) && $data['expires'] < $now) {
                unset($_SESSION[self::$sessionKey][$key]);
            }
        }
    }
}

/**
 * دوال مختصرة للكاش
 */
function cache_get($key, $type = 'memory') {
    return SimpleCache::get($key, $type);
}

function cache_set($key, $value, $type = 'memory', $ttl = 300) {
    SimpleCache::set($key, $value, $type, $ttl);
}

function cache_remember($key, $callback, $type = 'memory', $ttl = 300) {
    return SimpleCache::remember($key, $callback, $type, $ttl);
}

function cache_clear($key = null, $type = 'memory') {
    SimpleCache::delete($key, $type);
}
