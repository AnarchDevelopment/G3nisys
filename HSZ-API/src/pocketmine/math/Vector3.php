<?php
// declare(strict_types=1);

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

namespace pocketmine\math;

use pocketmine\utils\Random;


class Vector3 implements \JsonSerializable {
    const SIDE_DOWN = 0;
    const SIDE_UP = 1;
    const SIDE_NORTH = 2;
    const SIDE_SOUTH = 3;
    const SIDE_WEST = 4;
    const SIDE_EAST = 5;

    const EPSILON = 0.000001;
    const EPSILON_SQUARED = 1.0E-12;

    public $x;
    public $y;
    public $z;


    private $lengthSquaredCache = null;
    private $lengthCache = null;
    private $cacheValid = false;

    private static $pool = [];
    private static $poolIndex = 0;
    private static $poolEnabled = true;
    const MAX_POOL_SIZE = 1024;

    public function __construct($x = 0, $y = 0, $z = 0){
        $this->x = (float) $x;
        $this->y = (float) $y;
        $this->z = (float) $z;
    }

    public static function create($x = 0, $y = 0, $z = 0){
        if(self::$poolEnabled && self::$poolIndex < self::MAX_POOL_SIZE){
            if(self::$poolIndex >= count(self::$pool)){
                self::$pool[] = new self();
            }
            $vec = self::$pool[self::$poolIndex];
            $vec->setComponents($x, $y, $z);
            self::$poolIndex++;
            return $vec;
        }
        return new self($x, $y, $z);
    }


    public static function resetPool(){
        self::$poolIndex = 0;
    }

    public static function setPoolEnabled($enabled){
        self::$poolEnabled = (bool) $enabled;
    }

   
    public function setComponents($x, $y, $z){
        $this->x = (float) $x;
        $this->y = (float) $y;
        $this->z = (float) $z;
        $this->cacheValid = false;
        return $this;
    }


    public function invalidateCache(){
        $this->cacheValid = false;
        $this->lengthSquaredCache = null;
        $this->lengthCache = null;
    }


    public function getX(){ return $this->x; }
    public function getY(){ return $this->y; }
    public function getZ(){ return $this->z; }

    public function getFloorX(){ return (int) floor($this->x); }
    public function getFloorY(){ return (int) floor($this->y); }
    public function getFloorZ(){ return (int) floor($this->z); }

    public function add($x, $y = 0, $z = 0){
        if($x instanceof self){
            return self::create($this->x + $x->x, $this->y + $x->y, $this->z + $x->z);
        }
        return self::create($this->x + $x, $this->y + $y, $this->z + $z);
    }

    public function addSelf($x, $y = 0, $z = 0){
        if($x instanceof self){
            $this->x += $x->x;
            $this->y += $x->y;
            $this->z += $x->z;
        }else{
            $this->x += $x;
            $this->y += $y;
            $this->z += $z;
        }
        $this->invalidateCache();
        return $this;
    }

    public function subtract($x = 0, $y = 0, $z = 0){
        if($x instanceof self){
            return $this->add(-$x->x, -$x->y, -$x->z);
        }
        return $this->add(-$x, -$y, -$z);
    }


    public function subtractSelf($x = 0, $y = 0, $z = 0){
        if($x instanceof self){
            $this->x -= $x->x;
            $this->y -= $x->y;
            $this->z -= $x->z;
        }else{
            $this->x -= $x;
            $this->y -= $y;
            $this->z -= $z;
        }
        $this->invalidateCache();
        return $this;
    }

    public function multiply($number){
        return self::create($this->x * $number, $this->y * $number, $this->z * $number);
    }

    public function multiplySelf($number){
        $this->x *= $number;
        $this->y *= $number;
        $this->z *= $number;
        $this->invalidateCache();
        return $this;
    }

    public function divide($number){
        return $number == 0 ? self::create() : self::create($this->x / $number, $this->y / $number, $this->z / $number);
    }

    public function divideSelf($number){
        if($number != 0){
            $this->x /= $number;
            $this->y /= $number;
            $this->z /= $number;
            $this->invalidateCache();
        }
        return $this;
    }

    public function lengthSquared(){
        if(!$this->cacheValid || $this->lengthSquaredCache === null){
            $this->lengthSquaredCache = $this->x * $this->x + $this->y * $this->y + $this->z * $this->z;
            $this->cacheValid = true;
        }
        return $this->lengthSquaredCache;
    }

    public function length(){
        if(!$this->cacheValid || $this->lengthCache === null){
            $this->lengthCache = sqrt($this->lengthSquared());
        }
        return $this->lengthCache;
    }

    public function distanceSquared(Vector3 $pos){
        $dx = $this->x - $pos->x;
        $dy = $this->y - $pos->y;
        $dz = $this->z - $pos->z;
        return $dx * $dx + $dy * $dy + $dz * $dz;
    }

    public function distance(Vector3 $pos){
        return sqrt($this->distanceSquared($pos));
    }

    public function normalize(){
        $len = $this->length();
        return $len > 0 ? $this->divide($len) : self::create();
    }

    public function normalizeSelf(){
        $len = $this->length();
        if($len > 0){
            $this->divideSelf($len);
        }else{
            $this->x = $this->y = $this->z = 0;
        }
        return $this;
    }

    public function dot(Vector3 $v){
        return $this->x * $v->x + $this->y * $v->y + $this->z * $v->z;
    }

    public function cross(Vector3 $v){
        return self::create(
            $this->y * $v->z - $this->z * $v->y,
            $this->z * $v->x - $this->x * $v->z,
            $this->x * $v->y - $this->y * $v->x
        );
    }


    public function ceil(){ return self::create((int) ceil($this->x), (int) ceil($this->y), (int) ceil($this->z)); }
    public function floor(){ return self::create((int) floor($this->x), (int) floor($this->y), (int) floor($this->z)); }
    public function round(){ return self::create((int) round($this->x), (int) round($this->y), (int) round($this->z)); }
    public function abs(){ return self::create(abs($this->x), abs($this->y), abs($this->z)); }

    public function clampMagnitude($maxLength){
        $sqrLen = $this->lengthSquared();
        if($sqrLen > $maxLength * $maxLength){
            return $this->normalize()->multiply($maxLength);
        }
        return clone $this;
    }

    public static function lerp(Vector3 $from, Vector3 $to, $t){
        $t = max(0, min(1, $t));
        return self::create(
            $from->x + ($to->x - $from->x) * $t,
            $from->y + ($to->y - $from->y) * $t,
            $from->z + ($to->z - $from->z) * $t
        );
    }

    public function angleTo(Vector3 $other){
        $dot = $this->dot($other);
        $len = $this->length() * $other->length();
        return $len > 0 ? acos(min(1, max(-1, $dot / $len))) : 0.0;
    }

    public function getSide($side, $step = 1){
        switch((int) $side){
            case self::SIDE_DOWN:
                return self::create($this->x, $this->y - $step, $this->z);
            case self::SIDE_UP:
                return self::create($this->x, $this->y + $step, $this->z);
            case self::SIDE_NORTH:
                return self::create($this->x, $this->y, $this->z - $step);
            case self::SIDE_SOUTH:
                return self::create($this->x, $this->y, $this->z + $step);
            case self::SIDE_WEST:
                return self::create($this->x - $step, $this->y, $this->z);
            case self::SIDE_EAST:
                return self::create($this->x + $step, $this->y, $this->z);
            default:
                return clone $this;
        }
    }

    public static function getOppositeSide($side){
        switch((int) $side){
            case self::SIDE_DOWN:
                return self::SIDE_UP;
            case self::SIDE_UP:
                return self::SIDE_DOWN;
            case self::SIDE_NORTH:
                return self::SIDE_SOUTH;
            case self::SIDE_SOUTH:
                return self::SIDE_NORTH;
            case self::SIDE_WEST:
                return self::SIDE_EAST;
            case self::SIDE_EAST:
                return self::SIDE_WEST;
            default:
                return -1;
        }
    }



    public function getIntermediateWithXValue(Vector3 $v, $x){
        $xDiff = $v->x - $this->x;
        if(abs($xDiff) < self::EPSILON){
            return null;
        }

        $f = ($x - $this->x) / $xDiff;
        if($f < 0 || $f > 1){
            return null;
        }

        $yDiff = $v->y - $this->y;
        $zDiff = $v->z - $this->z;
        return self::create(
            $this->x + $xDiff * $f,
            $this->y + $yDiff * $f,
            $this->z + $zDiff * $f
        );
    }

    public function getIntermediateWithYValue(Vector3 $v, $y){
        $yDiff = $v->y - $this->y;
        if(abs($yDiff) < self::EPSILON){
            return null;
        }

        $f = ($y - $this->y) / $yDiff;
        if($f < 0 || $f > 1){
            return null;
        }

        $xDiff = $v->x - $this->x;
        $zDiff = $v->z - $this->z;
        return self::create(
            $this->x + $xDiff * $f,
            $this->y + $yDiff * $f,
            $this->z + $zDiff * $f
        );
    }

    public function getIntermediateWithZValue(Vector3 $v, $z){
        $zDiff = $v->z - $this->z;
        if(abs($zDiff) < self::EPSILON){
            return null;
        }

        $f = ($z - $this->z) / $zDiff;
        if($f < 0 || $f > 1){
            return null;
        }

        $xDiff = $v->x - $this->x;
        $yDiff = $v->y - $this->y;
        return self::create(
            $this->x + $xDiff * $f,
            $this->y + $yDiff * $f,
            $this->z + $zDiff * $f
        );
    }



    public function equals(Vector3 $v, $epsilon = self::EPSILON){
        return abs($this->x - $v->x) < $epsilon &&
               abs($this->y - $v->y) < $epsilon &&
               abs($this->z - $v->z) < $epsilon;
    }

    
    public function exactEquals(Vector3 $v){
        return $this->x === $v->x && $this->y === $v->y && $this->z === $v->z;
    }



    public function __toString(){
        return "Vector3(x=" . $this->x . ",y=" . $this->y . ",z=" . $this->z . ")";
    }

    public function jsonSerialize(){
        return ['x' => $this->x, 'y' => $this->y, 'z' => $this->z];
    }

    public static function fromArray(array $data){
        return self::create(
            isset($data['x']) ? $data['x'] : 0,
            isset($data['y']) ? $data['y'] : 0,
            isset($data['z']) ? $data['z'] : 0
        );
    }

  
    public static function createRandomDirection(Random $random){
        return VectorMath::getDirection3D($random->nextFloat() * 2 * M_PI, $random->nextFloat() * 2 * M_PI);
    }



    public static function createRandomDirectionInHemisphere(Vector3 $normal, Random $random){
        $dir = self::createRandomDirection($random);
        if($dir->dot($normal) < 0){
            $dir->multiplySelf(-1);
        }
        return $dir;
    }


    public function reflect(Vector3 $normal){
        $dot = $this->dot($normal);
        return self::create(
            $this->x - 2 * $dot * $normal->x,
            $this->y - 2 * $dot * $normal->y,
            $this->z - 2 * $dot * $normal->z
        );
    }
}


class Vector3Pipeline {
    /** @var Vector3[] */
    private $vectors = [];
    private $count = 0;

    public function add(Vector3 $vec){
        $this->vectors[] = $vec;
        $this->count++;
    }

    public function clear(){
        $this->vectors = [];
        $this->count = 0;
        Vector3::resetPool();
    }

  
    public function batchAdd($x, $y, $z){
        for($i = 0; $i < $this->count; $i++){
            $this->vectors[$i]->addSelf($x, $y, $z);
        }
    }


    public function batchMultiply($scalar){
        for($i = 0; $i < $this->count; $i++){
            $this->vectors[$i]->multiplySelf($scalar);
        }
    }



    public function batchNormalize(){
        for($i = 0; $i < $this->count; $i++){
            $vec = $this->vectors[$i];
            $len = $vec->length();
            if($len > 0){
                $invLen = 1.0 / $len;
                $vec->x *= $invLen;
                $vec->y *= $invLen;
                $vec->z *= $invLen;
                $vec->invalidateCache();
            }
        }
    }



    public function batchApplyGravity(Vector3 $center, $strength, $minDistance = 0.1){
        for($i = 0; $i < $this->count; $i++){
            $vec = $this->vectors[$i];
            $dx = $center->x - $vec->x;
            $dy = $center->y - $vec->y;
            $dz = $center->z - $vec->z;
            
            $distSq = $dx * $dx + $dy * $dy + $dz * $dz;
            if($distSq > $minDistance * $minDistance){
                $force = $strength / $distSq;
                $vec->x += $dx * $force;
                $vec->y += $dy * $force;
                $vec->z += $dz * $force;
                $vec->invalidateCache();
            }
        }
    }


    public function batchApplyDrag($drag){
        $dragFactor = 1.0 - $drag;
        if($dragFactor < 0) $dragFactor = 0;
        if($dragFactor > 1) $dragFactor = 1;
        
        for($i = 0; $i < $this->count; $i++){
            $vec = $this->vectors[$i];
            $vec->x *= $dragFactor;
            $vec->y *= $dragFactor;
            $vec->z *= $dragFactor;
            $vec->invalidateCache();
        }
    }

  
    public function batchClampMagnitude($maxLength){
        $maxSquared = $maxLength * $maxLength;
        for($i = 0; $i < $this->count; $i++){
            $vec = $this->vectors[$i];
            $lenSq = $vec->lengthSquared();
            if($lenSq > $maxSquared){
                $len = sqrt($lenSq);
                $scale = $maxLength / $len;
                $vec->x *= $scale;
                $vec->y *= $scale;
                $vec->z *= $scale;
                $vec->invalidateCache();
            }
        }
    }


    public function batchProjectOnPlane(Vector3 $planeNormal){
        $normal = $planeNormal->normalize();
        for($i = 0; $i < $this->count; $i++){
            $vec = $this->vectors[$i];
            $dot = $vec->dot($normal);
            $vec->x -= $dot * $normal->x;
            $vec->y -= $dot * $normal->y;
            $vec->z -= $dot * $normal->z;
            $vec->invalidateCache();
        }
    }


    public function getSum(){
        $sum = Vector3::create();
        for($i = 0; $i < $this->count; $i++){
            $sum->addSelf($this->vectors[$i]);
        }
        return $sum;
    }

    public function getAverage(){
        if($this->count === 0){
            return Vector3::create();
        }
        $sum = $this->getSum();
        return $sum->divide($this->count);
    }
}