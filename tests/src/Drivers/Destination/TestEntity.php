<?php


namespace DragoonBoots\A2B\Tests\Drivers\Destination;


class TestEntity
{

    protected $id;

    protected $field1;

    protected $field2;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     *
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getField1()
    {
        return $this->field1;
    }

    /**
     * @param mixed $field1
     *
     * @return self
     */
    public function setField1($field1)
    {
        $this->field1 = $field1;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getField2()
    {
        return $this->field2;
    }

    /**
     * @param mixed $field2
     *
     * @return self
     */
    public function setField2($field2)
    {
        $this->field2 = $field2;

        return $this;
    }
}
