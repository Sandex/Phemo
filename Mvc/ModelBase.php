<?php

namespace Phemo\Mvc;

use Phalcon\Filter;
use Phalcon\Mvc\Model;
use Phalcon\Text;

/**
 * Base Model Product
 */
class ModelBase extends Model
{

    /**
     * Bind variables to model
     *
     * @param array $vars
     */
    public function bind(array $vars)
    {
        $filter = new Filter();

        foreach ($vars as $filed => $value) {
            $attribute = Text::camelize($filed);
            $setter = 'set' . $attribute;

            $value = $filter->sanitize($value, 'string');

            if ($value) {
                $this->$setter($value);
            }
        }

        /*
          if (isset($vars['name'])) {
          $this->setName($filter->sanitize($vars['name'], 'string'));
          }
          if (isset($vars['category_id'])) {
          $this->setName($filter->sanitize($vars['category_id'], 'int'));
          }
         */
    }

    /**
     * Build criteria
     *
     * @param array $vars
     * @return type
     */
    public function getCriteria(array $vars)
    {
        $criteria = $this->query();

        $bind = [];
        foreach ($vars as $key => $value) {
            if ($value) {
                $bind[$key] = $value;
                $criteria->andWhere($key . ' = :' . $key . ':');
            }
        }
        $criteria->bind($bind);


        /*
          $criteria = $this->query();

          $criteria->where("product_id = :type:")
          ->andWhere("year < 2000")
          ->bind(["type" => "mechanical"])
          ->order("name")
          ->execute(); /* */

        return $criteria;
    }

}
