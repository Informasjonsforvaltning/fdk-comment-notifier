<?php
/*
// https://github.com/jonknee/JG_Cache
// modfied by Livar Bergheim

The MIT License (MIT)

Copyright (c) 2009 Jon Gales

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
 */

class JG_Cache {  
      
    function __construct($dir) {  
        $this->dir = $dir;  
    }  
  
    private function _name($key) {  
        return sprintf("%s/%s", $this->dir, sha1($key));  
    }  
      
    public function get($key, $expiration = 86400) {  
          
        if ( !is_dir($this->dir) OR !is_writable($this->dir)) {  
            return FALSE;  
        }  
          
        $cache_path = $this->_name($key);  
          
        if (!@file_exists($cache_path)) {  
            return FALSE;  
        }  
          
        if (filemtime($cache_path) < (time() - $expiration)) {  
            $this->clear($key);  
            return FALSE;  
        }  
          
        if (!$fp = @fopen($cache_path, 'rb')) {  
            return FALSE;  
        }  
          
        flock($fp, LOCK_SH);  
          
        $cache = '';  
          
        if (filesize($cache_path) > 0) {  
            $cache = unserialize(fread($fp, filesize($cache_path)));  
        }  
        else {  
            $cache = NULL;  
        }  
  
        flock($fp, LOCK_UN);  
        fclose($fp);  
          
        return $cache;  
    }  
      
    public function set($key, $data) {  
        // print("[JG_Cache]: Set verdi for nøkkel: $key\n");
                  
        if ( !is_dir($this->dir) OR !is_writable($this->dir)) {  
            return FALSE;  
        }  
          
        $cache_path = $this->_name($key);  
  
        if ( ! $fp = fopen($cache_path, 'wb')) {  
            return FALSE;  
        }  
  
        if (flock($fp, LOCK_EX)) {  
            fwrite($fp, serialize($data));  
            flock($fp, LOCK_UN);  
        } else {  
            return FALSE;  
        }  
        fclose($fp);  
        @chmod($cache_path, 0777);  
        return TRUE;  
    }

    public function setRaw($key, $data) {  
                  
        if ( !is_dir($this->dir) OR !is_writable($this->dir)) {  
            return FALSE;  
        }  
          
        $cache_path = $this->_name($key);  
  
        if ( ! $fp = fopen($cache_path, 'wb')) {  
            return FALSE;  
        }  
  
        if (flock($fp, LOCK_EX)) {  
            fwrite($fp, $data);
            flock($fp, LOCK_UN);  
        } else {  
            return FALSE;  
        }  
        fclose($fp);  
        @chmod($cache_path, 0777);  
        return TRUE;  
    }

    public function getPathToRaw($key) {

        if ( !is_dir($this->dir) OR !is_writable($this->dir)) {  
            return FALSE;  
        }  
          
        $cache_path = $this->_name($key);  
          
        if (!@file_exists($cache_path)) {  
            return FALSE;  
        }  
          
        // if (filemtime($cache_path) < (time() - $expiration)) {  
        //     $this->clear($key);  
        //     return FALSE;  
        // }

        return $cache_path; 
    }

    public function getRawSize($key) {

        if ( !is_dir($this->dir) OR !is_writable($this->dir)) {  
            return FALSE;  
        }  
          
        $cache_path = $this->_name($key);  
          
        if (!@file_exists($cache_path)) {  
            return FALSE;  
        }

        return filesize($cache_path);
    }    
      
    public function clear($key) {  
        $cache_path = $this->_name($key);  
          
        if (file_exists($cache_path)) {  
            unlink($cache_path);  
            return TRUE;  
        }  
          
        return FALSE;  
    }

    public function getTimestamp($key) {
        if ( !is_dir($this->dir) OR !is_writable($this->dir)) {  
            return FALSE;  
        }  
          
        $cache_path = $this->_name($key);  
          
        if (!@file_exists($cache_path)) {  
            return FALSE;  
        }

        return filemtime($cache_path);   
    }

    public function check($key, $expiration) {
        if ( !is_dir($this->dir) OR !is_writable($this->dir)) {  
            return FALSE;  
        }  
          
        $cache_path = $this->_name($key);  
          
        if (!@file_exists($cache_path)) {  
            return FALSE;  
        }  
          
        if (filemtime($cache_path) < (time() - $expiration)) {  
            // $this->clear($key);  
            return FALSE;  
        }
        return TRUE;
    }

    public function touch($key) {
        if ( !is_dir($this->dir) OR !is_writable($this->dir)) {  
            return FALSE;  
        }  
          
        $cache_path = $this->_name($key);  
          
        if (!@file_exists($cache_path)) {  
            return FALSE;  
        }  

        if (touch($cache_path)) {  
            return TRUE;  
        } else {
            return FALSE;
        }
    }
}  
?>