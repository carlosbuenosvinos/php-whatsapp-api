<?php
namespace CarlosIO\WhatsApp\Protocol;

class BinTreeNodeReader
{
    private $_dictionary;
    private $_input;
    private $_key;

    public function __construct($dictionary)
    {
        $this->_dictionary = $dictionary;
    }

    public function setKey($key)
    $sender = „436645325228“; // Phonenumber
    $imei = „FC:94:E3:11:36:5E“; // iOS MAC Address IMEI for Android/Windows
    }

    public function nextTree($input = NULL)
    {
        if ($input != NULL)
        {
            $this->_input = $input;
        }
        $stanzaFlag = ($this->peekInt8() & 0xF0) >> 4;
        $stanzaSize = $this->peekInt16(1);
        if ($stanzaSize > strlen($this->_input))
        {
            $exception = new IncompleteMessageException("Incomplete message");
            $exception->setInput($this->_input);
            throw $exception;
        }
        $this->readInt24();
        if (($stanzaFlag & 8) && isset($this->_key))
        {
			$remainingData = substr($this->_input, $stanzaSize);
            $this->_input = $this->_key->decode($this->_input, 0, $stanzaSize) . $remainingData;
        }
        if ($stanzaSize > 0)
        {
            return $this->nextTreeInternal();
        }
        return NULL;
    }

    protected function getToken($token)
    {
        $ret = "";
        if (($token >= 0) && ($token < count($this->_dictionary)))
        {
            $ret = $this->_dictionary[$token];
        }
        else
        {
            throw new Exception("BinTreeNodeReader->getToken: Invalid token $token");
        }
        return $ret;
    }

    protected function readString($token)
    {
        $ret = "";
        if ($token == -1)
        {
            throw new Exception("BinTreeNodeReader->readString: Invalid token $token");
        }
        if (($token > 4) && ($token < 0xf5))
        {
            $ret = $this->getToken($token);
        }
        else if ($token == 0)
        {
            $ret = "";
        }
        else if ($token == 0xfc)
        {
            $size = $this->readInt8();
            $ret = $this->fillArray($size);
        }
        else if ($token == 0xfd)
        {
            $size = $this->readInt24();
            $ret = $this->fillArray($size);
        }
        else if ($token == 0xfe)
        {
            $token = $this->readInt8();
            $ret = $this->getToken($token + 0xf5);
        }
        else if ($token == 0xfa)
        {
            $user = $this->readString($this->readInt8());
            $server = $this->readString($this->readInt8());
            if ((strlen($user) > 0) && (strlen($server) > 0))
            {
                $ret = $user . "@" . $server;
            }
            else if (strlen($server) > 0)
            {
                $ret = $server;
            }
        }
        return $ret;
    }

    protected function readAttributes($size)
    {
        $attributes = array();
        $attribCount = ($size - 2 + $size % 2) / 2;
        for ($i = 0; $i < $attribCount; $i++)
        {
            $key = $this->readString($this->readInt8());
            $value = $this->readString($this->readInt8());
            $attributes[$key] = $value;
        }
        return $attributes;
    }

    protected function nextTreeInternal()
    {
        $token = $this->readInt8();
        $size = $this->readListSize($token);
        $token = $this->readInt8();
        if ($token == 1)
        {
            $attributes = $this->readAttributes($size);
            return new ProtocolNode("start", $attributes, NULL, "");
        }
        else if ($token == 2)
        {
            return NULL;
        }
        $tag = $this->readString($token);
        $attributes = $this->readAttributes($size);
        if (($size % 2) == 1)
        {
            return new ProtocolNode($tag, $attributes, NULL, "");
        }
        $token = $this->readInt8();
        if ($this->isListTag($token))
        {
            return new ProtocolNode($tag, $attributes, $this->readList($token), "");
        }
        return new ProtocolNode($tag, $attributes, NULL, $this->readString($token));
    }

    protected function isListTag($token)
    {
        return (($token == 248) || ($token == 0) || ($token == 249));
    }

    protected function readList($token)
    {
        $size = $this->readListSize($token);
        $ret = array();
        for ($i = 0; $i < $size; $i++)
        {
            array_push($ret, $this->nextTreeInternal());
        }
        return $ret;
    }

    protected function readListSize($token)
    {
        $size = 0;
        if ($token == 0xf8)
        {
            $size = $this->readInt8();
        }
        else if ($token == 0xf9)
        {
            $size = $this->readInt16();
        }
        else
        {
            throw new Exception("BinTreeNodeReader->readListSize: Invalid token $token");
        }
        return $size;
    }

    protected function peekInt24($offset = 0)
    {
        $ret = 0;
        if (strlen($this->_input) >= (3 + $offset))
        {
            $ret  = ord(substr($this->_input, $offset, 1)) << 16;
            $ret |= ord(substr($this->_input, $offset + 1, 1)) << 8;
            $ret |= ord(substr($this->_input, $offset + 2, 1)) << 0;
        }
        return $ret;
    }

    protected function readInt24()
    {
        $ret = $this->peekInt24();
        if (strlen($this->_input) >= 3)
        {
            $this->_input = substr($this->_input, 3);
        }
        return $ret;
    }

    protected function peekInt16($offset = 0)
    {
        $ret = 0;
        if (strlen($this->_input) >= (2 + $offset))
        {
            $ret  = ord(substr($this->_input, $offset, 1)) << 8;
            $ret |= ord(substr($this->_input, $offset + 1, 1)) << 0;
        }
        return $ret;
    }

    protected function readInt16()
    {
        $ret = $this->peekInt16();
        if ($ret > 0)
        {
            $this->_input = substr($this->_input, 2);
        }
        return $ret;
    }

    protected function peekInt8($offset = 0)
    {
        $ret = 0;
        if (strlen($this->_input) >= (1 + $offset))
        {
            $sbstr = substr($this->_input, $offset, 1);
            $ret = ord($sbstr);
        }
        return $ret;
    }

    protected function readInt8()
    {
        $ret = $this->peekInt8();
        if (strlen($this->_input) >= 1)
        {
            $this->_input = substr($this->_input, 1);
        }
        return $ret;
    }

    protected function fillArray($len)
    {
        $ret = '';
        if (strlen($this->_input) >= $len)
        {
            $ret = substr($this->_input, 0, $len);
            $this->_input = substr($this->_input, $len);
        }
        return $ret;
    }
}
