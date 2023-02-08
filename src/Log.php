<?php
namespace Abstracts;

use \Abstracts\Helpers\Initialize;
use \Abstracts\Helpers\Database;
use \Abstracts\Helpers\Validation;
use \Abstracts\Helpers\Translation;
use \Abstracts\Helpers\Utilities;

use \Abstracts\API;

use Exception;

class Log {

  /* configuration */
  public $id = "11";
  public $public_functions = array();
  public $module = null;

  /* core */
  private $config = null;
  private $session = null;
  private $controls = null;

  /* helpers */
  private $database = null;
  private $validation = null;
  private $translation = null;

  /* services */
  private $api = null;

  function __construct(
    $session = null,
    $controls = null
  ) {

    /* initialize: core */
    $initialize = new Initialize($session, $controls, $this->id);
    $this->config = $initialize->config;
    $this->session = $initialize->session;
    $this->controls = $initialize->controls;
    $this->module = $initialize->module;
    
    /* initialize: helpers */
    $this->database = new Database($this->session, $this->controls);
    $this->validation = new Validation();
    $this->translation = new Translation();

    /* initialize: services */
    $this->api = new API($this->session, 
      Utilities::override_controls(true, true, true, true)
    );

  }

  function request($function, $parameters) {
    $result = null;
    if ($this->api->authorize($this->id, $function, $this->public_functions)) {
      if ($function == "get") {
        $result = $this->$function(
          (isset($parameters["id"]) ? $parameters["id"] : null),
          (isset($parameters["return_references"]) ? $parameters["return_references"] : false)
        );
      } else if ($function == "list") {
        $result = $this->$function(
          (isset($parameters["start"]) ? $parameters["start"] : null), 
          (isset($parameters["limit"]) ? $parameters["limit"] : null), 
          (isset($parameters["sort_by"]) ? $parameters["sort_by"] : null), 
          (isset($parameters["sort_direction"]) ? $parameters["sort_direction"] : null), 
          (isset($parameters) ? $parameters : null), 
          (isset($parameters["extensions"]) ? $parameters["extensions"] : null), 
          (isset($parameters["return_references"]) ? $parameters["return_references"] : false)
        );
      } else if ($function == "count") {
        $result = $this->$function(
          (isset($parameters["start"]) ? $parameters["start"] : null), 
          (isset($parameters["limit"]) ? $parameters["limit"] : null), 
          (isset($parameters) ? $parameters : null), 
          (isset($parameters["extensions"]) ? $parameters["extensions"] : null)
        );
      } else if ($function == "log") {
        $result = $this->$function($parameters);
      } else if ($function == "create") {
        $result = $this->$function($parameters);
      } else if ($function == "update") {
        $result = $this->$function(
          (isset($parameters["id"]) ? $parameters["id"] : null),
          (isset($parameters) ? $parameters : null)
        );
      } else if ($function == "delete") {
        $result = $this->$function(
          (isset($parameters["id"]) ? $parameters["id"] : null)
        );
      } else if ($function == "patch") {
        $result = $this->$function(
          (isset($parameters["id"]) ? $parameters["id"] : null),
          (isset($parameters) ? $parameters : null)
        );
      } else if ($function == "upload") {
        $result = $this->$function(
          (isset($parameters["id"]) ? $parameters["id"] : null),
          $_FILES
        );
      } else if ($function == "remove") {
        $result = $this->$function(
          (isset($parameters["id"]) ? $parameters["id"] : null),
          (isset($parameters) ? $parameters : null)
        );
      } else if ($function == "data") {
        $result = $this->$function(
          (isset($parameters["key"]) ? $parameters["key"] : null),
          (isset($parameters["value"]) ? $parameters["value"] : null)
        );
      } else {
        throw new Exception($this->translation->translate("Function not supported"), 421);
      }
    }
    return $result;
  }

  function log(
    $name, 
    $function, 
    $violation, 
    $content_hash, 
    $module_id, 
    $module_key, 
    $module_value
  ) {

    try {

      $link = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on" ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];

      $type = "server";
      if (isset(debug_backtrace()[2]["function"]) && debug_backtrace()[2]["function"] == "request") {
        $type = "request";
      }

      $log = false;
      if ($type == "server" && !empty($this->config["server_log"])) {
        if (
          is_array($this->config["server_log"])
          && in_array($violation, $this->config["server_log"])
        ) {
          $log = true;
        } else if ($this->config["server_log"] == "low" && $violation == "low") {
          $log = true;
        } else if ($this->config["server_log"] === true) {
          $log = true;
        }
      } else if ($type == "request" && !empty($this->config["log"])) {
        if (
          is_array($this->config["log"])
          && in_array($violation, $this->config["log"])
        ) {
          $log = true;
        } else if ($this->config["log"] == "low" && $violation == "low") {
          $log = true;
        } else if ($this->config["log"] === true) {
          $log = true;
        }
      }

      if ($log === true) {

        $ip = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
          $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
          $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if (isset($_SERVER['HTTP_X_FORWARDED'])) {
          $ip = $_SERVER['HTTP_X_FORWARDED'];
        } else if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
          $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } else if (isset($_SERVER['HTTP_FORWARDED'])) {
          $ip = $_SERVER['HTTP_FORWARDED'];
        } else if (isset($_SERVER['REMOTE_ADDR'])) {
          $ip = $_SERVER['REMOTE_ADDR'];
        }
          
        /* initialize: parameters */
        $parameters = array(
          "name" => $name,
          "function" => $function,
          "violation" => $violation,
          "content_hash" => (is_array($content_hash) ? serialize($content_hash) : serialize(array($content_hash))),
          "link" => $link,
          "type" => $type,
          "ip" => $ip,
          "user_agent" => $_SERVER["HTTP_USER_AGENT"],
          "module_id" => $module_id,
          "module_key" => $module_key,
          "module_value" => $module_value
        );
    
        if (!empty($data = $this->create($parameters))) {
          return $this->callback(
            __METHOD__, 
            func_get_args(), 
            $data
          );
        } else {
          return false;
        }

      } else {
        return false;
      }

    } catch (Exception $e) {
      return false;
    }

  }

  function get($id, $return_references = false) {
    if ($this->validation->require($id, "ID")) {

      $return_references = Initialize::return_references($return_references);

      $filters = array("id" => $id);
      $data = $this->database->select(
        "log", 
        "*", 
        $filters, 
        null, 
        $this->controls["view"]
      );
      if (!empty($data)) {
        $referers = $this->refer($return_references);
        return $this->callback(__METHOD__, func_get_args(), $this->format($data, $return_references, $referers));
      } else {
        return (object) array();
      }

    } else {
      return null;
    }
  }

  function list(
    $start = null, 
    $limit = null, 
    $sort_by = "id", 
    $sort_direction = "desc", 
    $filters = array(), 
    $extensions = array(),
    $return_references = false
  ) {

    $start = Initialize::start($start);
    $limit = Initialize::limit($limit);
    $sort_by = Initialize::sort_by($sort_by);
    $sort_direction = Initialize::sort_direction($sort_direction);
    $filters = Initialize::filters($filters);
    $extensions = Initialize::extensions($extensions);
    $return_references = Initialize::return_references($return_references);
    
    if (
      $this->validation->filters($filters) 
      && $this->validation->extensions($extensions)
    ) {
      $list = $this->database->select_multiple(
        "log", 
        "*", 
        $filters, 
        $extensions, 
        $start, 
        $limit, 
        $sort_by, 
        $sort_direction, 
        $this->controls["view"]
      );
      if (!empty($list)) {
        $referers = $this->refer($return_references);
        $data = array();
        foreach ($list as $value) {
          array_push($data, $this->format($value, $return_references, $referers));
        }
        return $this->callback(__METHOD__, func_get_args(), $data);
      } else {
        return array();
      }
    } else {
      return null;
    }
  }

  function count(
    $start = null, 
    $limit = null, 
    $filters = array(), 
    $extensions = array()
  ) {

    $start = Initialize::start($start);
    $limit = Initialize::limit($limit);
    $filters = Initialize::filters($filters);
    $extensions = Initialize::extensions($extensions);

    if (
      $this->validation->filters($filters) 
      && $this->validation->extensions($extensions)
    ) {
      if (
        $data = $this->database->count(
          "log", 
          $filters, 
          $extensions, 
          $start, 
          $limit, 
          $this->controls["view"]
        )
      ) {
        return $data;
      } else {
        return 0;
      }
    } else {
      return null;
    }
  }

  function create($parameters, $user_id = 0) {
      
    /* initialize: parameters */
    $parameters = $this->inform($parameters, false, $user_id);

    if ($this->validate($parameters)) {

      $data = $this->database->insert(
        "log", 
        $parameters, 
        $this->controls["create"]
      );
      if (!empty($data)) {
        return $this->callback(
          __METHOD__, 
          func_get_args(), 
          $this->format($data)
        );
      } else {
        return $data;
      }

    } else {
      return null;
    }

  }

  function update($id, $parameters) {

    /* initialize: parameters */
    $parameters = $this->inform($parameters, true);

    if ($this->validate($parameters, $id)) {
      $data = $this->database->update(
        "log", 
        $parameters, 
        array("id" => $id), 
        null, 
        $this->controls["update"]
      );
      if (!empty($data)) {
        $data = $data[0];
        return $this->callback(
          __METHOD__, 
          func_get_args(), 
          $this->format($data)
        );
      } else {
        return $data;
      }
    } else {
      return null;
    }

  }

  function patch($id, $parameters) {

    /* initialize: parameters */
    $parameters = $this->inform($parameters, true);
    
    if ($this->validation->require($id, "ID")) {

      if ($this->validate($parameters, $id, true)) {
        $data = $this->database->update(
          "log", 
          $parameters, 
          array("id" => $id), 
          null, 
          $this->controls["update"]
        );
        if (!empty($data)) {
          $data = $data[0];
          return $this->callback(
            __METHOD__, 
            func_get_args(), 
            $this->format($data)
          );
        } else {
          return $data;
        }
      } else {
        return null;
      }

    } else {
      return null;
    }

  }

  function delete($id) {
    if ($this->validation->require($id, "ID")) {
      if (
        $data = $this->database->delete(
          "log", 
          array("id" => $id), 
          null, 
          $this->controls["delete"]
        )
      ) {
        $data = $data[0];
        return $this->callback(
          __METHOD__, 
          func_get_args(), 
          $this->format($data)
        );
      } else {
        return null;
      }
    } else {
      return null;
    }
  }

  function inform($parameters, $update = false, $user_id = 0) {
    if (!empty($parameters)) {
      if (!$update) {
        if (isset($parameters["id"])) {
          $parameters["id"] = $parameters["id"];
        } else {
          $parameters["id"] = null;
        }
        $parameters["user_id"] = (!empty($user_id) ? $user_id : (!empty($this->session) ? $this->session->id : 0));
        $parameters["create_at"] = gmdate("Y-m-d H:i:s");
      } else {
        unset($parameters["id"]);
        unset($parameters["create_at"]);
      }
    }
    return $parameters;
  }

  function refer($return_references = false, $abstracts_override = null) {

    $data = array();
    
    if (!empty($return_references)) {
      if ($return_references === true || (is_array($return_references) && in_array("module_id", $return_references))) {
        $data["module_id"] = new Module($this->session, Utilities::override_controls(true, true, true, true));
      }
      if ($return_references === true || (is_array($return_references) && in_array("user_id", $return_references))) {
        $data["user_id"] = new User($this->session, Utilities::override_controls(true, true, true, true));
      }
    }

    return $this->callback(__METHOD__, func_get_args(), $data);

  }

  function format($data, $return_references = false, $referers = null) {
    if (!empty($data)) {

      if (isset($data->content_hash) && !empty($data->content_hash)) {
        if (is_array(unserialize($data->content_hash))) {
          $data->content_hash = unserialize($data->content_hash);
        }
      }

      if (is_array($referers) && !empty($referers)) {
        if ($return_references === true || (is_array($return_references) && in_array("module_id", $return_references))) {
          if (isset($referers["module_id"])) {
            $data->module_id_reference = $referers["module_id"]->format(
              $this->database->get_reference(
                $data->module_id, 
                "module", 
                "id"
              ),
              true
            );
          }
        }
        if ($return_references === true || (is_array($return_references) && in_array("user_id", $return_references))) {
          if (isset($referers["user_id"])) {
            $data->user_id_reference = $referers["user_id"]->format(
              $this->database->get_reference(
                $data->user_id,
                "user",
                "id"
              )
            );
          }
        }
      }
    }
		return $data;
  }

  function validate($parameters, $target_id = null, $patch = false) {
    if (!empty($parameters)) {
      return true;
    } else {
      throw new Exception($this->translation->translate("Bad request"), 400);
    }
  }

  function callback($function, $arguments, $result) {
    $names = explode("::", $function);
    $classes = explode("\\", $names[0]);
    $namespace = "\\" . $classes[0] . "\\" . "Callback" . "\\" . $classes[1];
    if (class_exists($namespace)) {
      if (method_exists($namespace, $names[1])) {
        $callback = new $namespace($this->session, $this->controls, $this->id);
        try {
          $function_name = $names[1];
          return $callback->$function_name($arguments, $result);
        } catch(Exception $e) {
          throw new Exception($e->getMessage(), $e->getCode());
        }
      } else {
        return $result;
      }
    } else {
      return $result;
    }
  }

}