<?php
namespace Abstracts;

use \Abstracts\Helpers\Initialize;
use \Abstracts\Helpers\Database;
use \Abstracts\Helpers\Validation;
use \Abstracts\Helpers\Translation;
use \Abstracts\Helpers\Utilities;

use \Abstracts\API;
use \Abstracts\Control;
use \Abstracts\Log;

use Exception;

class Group {

  /* configuration */
  public $id = "7";
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
  private $control = null;
  private $log = null;

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
    $this->control = new Control($this->session, 
      Utilities::override_controls(true, true, true, true)
    );
    $this->log = new Log($this->session, 
      Utilities::override_controls(true, true, true, true)
    );

  }

  function request($function, $parameters) {
    $result = null;
    if ($this->api->authorize($this->id, $function, $this->public_functions)) {
      if ($function == "get") {
        $result = $this->$function(
          (isset($parameters["id"]) ? $parameters["id"] : null),
          (isset($parameters["active"]) ? $parameters["active"] : null),
          (isset($parameters["return_references"]) ? $parameters["return_references"] : false)
        );
      } else if ($function == "list") {
        $result = $this->$function(
          (isset($parameters["start"]) ? $parameters["start"] : null), 
          (isset($parameters["limit"]) ? $parameters["limit"] : null), 
          (isset($parameters["sort_by"]) ? $parameters["sort_by"] : null), 
          (isset($parameters["sort_direction"]) ? $parameters["sort_direction"] : null), 
          (isset($parameters["active"]) ? $parameters["active"] : null), 
          (isset($parameters) ? $parameters : null), 
          (isset($parameters["extensions"]) ? $parameters["extensions"] : null), 
          (isset($parameters["return_references"]) ? $parameters["return_references"] : false)
        );
      } else if ($function == "members") {
        $result = $this->$function(
          (isset($parameters["id"]) ? $parameters["id"] : null), 
          (isset($parameters["start"]) ? $parameters["start"] : null), 
          (isset($parameters["limit"]) ? $parameters["limit"] : null), 
          (isset($parameters["sort_by"]) ? $parameters["sort_by"] : null), 
          (isset($parameters["sort_direction"]) ? $parameters["sort_direction"] : null), 
          (isset($parameters["active"]) ? $parameters["active"] : null), 
          (isset($parameters) ? $parameters : null), 
          (isset($parameters["extensions"]) ? $parameters["extensions"] : null), 
          (isset($parameters["return_references"]) ? $parameters["return_references"] : false)
        );
      } else if ($function == "add_member") {
        $result = $this->$function(
          (isset($parameters["id"]) ? $parameters["id"] : null), 
          (isset($parameters["user"]) ? $parameters["user"] : null), 
        );
      } else if ($function == "remove_member") {
        $result = $this->$function(
          (isset($parameters["id"]) ? $parameters["id"] : null), 
          (isset($parameters["user"]) ? $parameters["user"] : null)
        );
      } else if ($function == "count") {
        $result = $this->$function(
          (isset($parameters["start"]) ? $parameters["start"] : null), 
          (isset($parameters["limit"]) ? $parameters["limit"] : null), 
          (isset($parameters["active"]) ? $parameters["active"] : null), 
          (isset($parameters) ? $parameters : null), 
          (isset($parameters["extensions"]) ? $parameters["extensions"] : null)
        );
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

  function get($id, $active = null, $return_references = false) {
    if ($this->validation->require($id, "ID")) {

      $active = Initialize::active($active);
      $return_references = Initialize::return_references($return_references);

      $filters = array("id" => $id);
      if (isset($active)) {
        $filters["active"] = $active;
      }
      $data = $this->database->select(
        "group", 
        "*", 
        $filters, 
        null, 
        $this->controls["view"]
      );
      if (!empty($data)) {
        $this->log->log(
          __FUNCTION__,
          __METHOD__,
          "low",
          func_get_args(),
          (!empty($this->module) && isset($this->module->id) ? $this->module->id : ""),
          "id",
          $data->id
        );
        return $this->callback(__METHOD__, func_get_args(), $this->format($data, $return_references));
      } else {
        return null;
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
    $active = null, 
    $filters = array(), 
    $extensions = array(),
    $return_references = false
  ) {

    $start = Initialize::start($start);
    $limit = Initialize::limit($limit);
    $sort_by = Initialize::sort_by($sort_by);
    $sort_direction = Initialize::sort_direction($sort_direction);
    $active = Initialize::active($active);
    $filters = Initialize::filters($filters);
    $extensions = Initialize::extensions($extensions);
    $return_references = Initialize::return_references($return_references);
    
    if (
      $this->validation->filters($filters) 
      && $this->validation->extensions($extensions)
    ) {
      if (isset($active)) {
        $filters["active"] = $active;
      }
      $list = $this->database->select_multiple(
        "group", 
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
        $data = array();
        foreach ($list as $value) {
          array_push($data, $this->format($value, $return_references));
        }
        $this->log->log(
          __FUNCTION__,
          __METHOD__,
          "low",
          func_get_args(),
          (!empty($this->module) && isset($this->module->id) ? $this->module->id : ""),
          null,
          null
        );
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
    $active = null, 
    $filters = array(), 
    $extensions = array()
  ) {

    $start = Initialize::start($start);
    $limit = Initialize::limit($limit);
    $active = Initialize::active($active);
    $filters = Initialize::filters($filters);
    $extensions = Initialize::extensions($extensions);

    if (
      $this->validation->filters($filters) 
      && $this->validation->extensions($extensions)
    ) {
      if (isset($active)) {
        $filters["active"] = $active;
      }
      if (
        $data = $this->database->count(
          "group", 
          $filters, 
          $extensions, 
          $start, 
          $limit, 
          $this->controls["view"]
        )
      ) {
        return $data;
      } else {
        return null;
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
        "group", 
        $parameters, 
        $this->controls["create"]
      );
      if (!empty($data)) {

        if (!empty($data->controls)) {
          $controls = unserialize($data->controls);
          foreach ($controls as $control) {
            $member_list = $this->database->select_multiple(
              "member", 
              "*", 
              array("group_id" => $data->id), 
              null, 
              null, 
              null, 
              "id", 
              "asc", 
              false,
              false
            );
            foreach ($member_list as $member_data) {
              $control_parameters = array(
                "id" => null,
                "user" => $member_data->user,
                "rules" => $control["rules"],
                "behaviors" => $control["behaviors"],
                "create_at" => gmdate("Y-m-d H:i:s"),
                "active" => true,
                "module_id" => $control["module_id"],
                "group_id" => $data->id,
                "user_id" => (!empty($this->session) ? $this->session->id : 0)
              );
              $controls_filters = array(
                "user" => $member_data->user,
                "module_id" => $control_parameters["module_id"]
              );
              $controls_extensions = array(
                array(
                  "conjunction" => "",
                  "key" => "rules",
                  "operator" => "LIKE",
                  "value" => "'%" . $control_parameters["rules"] . "%'"
                ),
                array(
                  "conjunction" => "AND",
                  "key" => "behaviors",
                  "operator" => "LIKE",
                  "value" => "'%" . $control_parameters["behaviors"] . "%'"
                )
              );
              if (empty(
                $this->database->select_multiple(
                  "control", 
                  "*", 
                  $controls_filters,
                  $controls_extensions,
                  null,
                  null,
                  null,
                  null,
                  null,
                  false
                )
              )) {
                if (empty(
                  $this->database->insert(
                    "control", 
                    $control_parameters, 
                    true,
                    false
                  )
                )) {
                  array_push($errors, $control_parameters);
                }
              }
            }
          }
        }

        $this->log->log(
          __FUNCTION__,
          __METHOD__,
          "normal",
          func_get_args(),
          (!empty($this->module) && isset($this->module->id) ? $this->module->id : ""),
          "id",
          $data->id
        );
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
        "group", 
        $parameters, 
        array("id" => $id), 
        null, 
        $this->controls["update"]
      );
      if (!empty($data)) {
        
        $data = $data[0];

        if (!empty($data->controls)) {
          $controls = unserialize($data->controls);
          foreach ($controls as $control) {
            $member_list = $this->database->select_multiple(
              "member", 
              "*", 
              array("group_id" => $data->id), 
              null, 
              null, 
              null, 
              "id", 
              "asc", 
              false,
              false
            );
            foreach ($member_list as $member_data) {
              $control_parameters = array(
                "id" => null,
                "user" => $member_data->user,
                "rules" => $control["rules"],
                "behaviors" => $control["behaviors"],
                "create_at" => gmdate("Y-m-d H:i:s"),
                "active" => true,
                "module_id" => $control["module_id"],
                "group_id" => $data->id,
                "user_id" => (!empty($this->session) ? $this->session->id : 0)
              );
              $controls_filters = array(
                "user" => $member_data->user,
                "module_id" => $control_parameters["module_id"]
              );
              $controls_extensions = array(
                array(
                  "conjunction" => "",
                  "key" => "rules",
                  "operator" => "LIKE",
                  "value" => "'%" . $control_parameters["rules"] . "%'"
                ),
                array(
                  "conjunction" => "AND",
                  "key" => "behaviors",
                  "operator" => "LIKE",
                  "value" => "'%" . $control_parameters["behaviors"] . "%'"
                )
              );
              if (empty(
                $this->database->select_multiple(
                  "control", 
                  "*", 
                  $controls_filters,
                  $controls_extensions,
                  null,
                  null,
                  null,
                  null,
                  null,
                  false
                )
              )) {
                if (empty(
                  $this->database->insert(
                    "control", 
                    $control_parameters, 
                    true,
                    false
                  )
                )) {
                  array_push($errors, $control_parameters);
                }
              }
            }
          }
        }
        
        $this->log->log(
          __FUNCTION__,
          __METHOD__,
          "normal",
          func_get_args(),
          (!empty($this->module) && isset($this->module->id) ? $this->module->id : ""),
          "id",
          $data->id
        );
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
          "group", 
          $parameters, 
          array("id" => $id), 
          null, 
          $this->controls["update"]
        );
        if (!empty($data)) {

          $data = $data[0];

          if (!empty($data->controls)) {
            $controls = unserialize($data->controls);
            foreach ($controls as $control) {
              $member_list = $this->database->select_multiple(
                "member", 
                "*", 
                array("group_id" => $data->id), 
                null, 
                null, 
                null, 
                "id", 
                "asc", 
                false,
                false
              );
              foreach ($member_list as $member_data) {
                $control_parameters = array(
                  "id" => null,
                  "user" => $member_data->user,
                  "rules" => $control["rules"],
                  "behaviors" => $control["behaviors"],
                  "create_at" => gmdate("Y-m-d H:i:s"),
                  "active" => true,
                  "module_id" => $control["module_id"],
                  "group_id" => $data->id,
                  "user_id" => (!empty($this->session) ? $this->session->id : 0)
                );
                $controls_filters = array(
                  "user" => $member_data->user,
                  "module_id" => $control_parameters["module_id"]
                );
                $controls_extensions = array(
                  array(
                    "conjunction" => "",
                    "key" => "rules",
                    "operator" => "LIKE",
                    "value" => "'%" . $control_parameters["rules"] . "%'"
                  ),
                  array(
                    "conjunction" => "AND",
                    "key" => "behaviors",
                    "operator" => "LIKE",
                    "value" => "'%" . $control_parameters["behaviors"] . "%'"
                  )
                );
                if (empty(
                  $this->database->select_multiple(
                    "control", 
                    "*", 
                    $controls_filters,
                    $controls_extensions,
                    null,
                    null,
                    null,
                    null,
                    null,
                    false
                  )
                )) {
                  if (empty(
                    $this->database->insert(
                      "control", 
                      $control_parameters, 
                      true,
                      false
                    )
                  )) {
                    array_push($errors, $control_parameters);
                  }
                }
              }
            }
          }

          $this->log->log(
            __FUNCTION__,
            __METHOD__,
            "normal",
            func_get_args(),
            (!empty($this->module) && isset($this->module->id) ? $this->module->id : ""),
            "id",
            $data->id
          );
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
          "group", 
          array("id" => $id), 
          null, 
          $this->controls["delete"]
        )
      ) {

        $data = $data[0];

        try {
          $this->database->delete(
            "member", 
            array("group_id" => $id), 
            null, 
            true
          );
        } catch (Exception $e) {}

        try {
          $this->database->delete(
            "control", 
            array("group_id" => $id), 
            null, 
            true
          );
        } catch (Exception $e) {}

        $this->log->log(
          __FUNCTION__,
          __METHOD__,
          "risk",
          func_get_args(),
          (!empty($this->module) && isset($this->module->id) ? $this->module->id : ""),
          "id",
          $data->id
        );
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

  function members(
    $id = null,
    $start = null, 
    $limit = null, 
    $sort_by = "id", 
    $sort_direction = "desc", 
    $active = null, 
    $filters = array(), 
    $extensions = array(),
    $return_references = false
  ) {

    $start = Initialize::start($start);
    $limit = Initialize::limit($limit);
    $sort_by = Initialize::sort_by($sort_by);
    $sort_direction = Initialize::sort_direction($sort_direction);
    $active = Initialize::active($active);
    $filters = Initialize::filters($filters);
    $extensions = Initialize::extensions($extensions);
    $return_references = Initialize::return_references($return_references);
    
    if (
      $this->validation->filters($filters) 
      && $this->validation->extensions($extensions)
    ) {
      unset($filters["id"]);
      $filters["group_id"] = $id;
      if (isset($active)) {
        $filters["active"] = $active;
      }
      $list = $this->database->select_multiple(
        "member", 
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

        $format = function ($data, $return_references = false) {

          if (!empty($data)) {

            if ($data->active === "1") {
              $data->active = true;
            } else if ($data->active === "0" || empty($data->active)) {
              $data->active = false;
            }

            if ($return_references === true || (is_array($return_references) && in_array("user_id", $return_references))) {
              $data->user_id_reference = $this->format(
                $this->database->get_reference(
                  $data->user_id, 
                  "user", 
                  "id"
                ),
                true
              );
            }

            $data->password_set = false;
            if (!empty($data->password)) {
              $data->password_set = true;
            }
            unset($data->password);

            $data->passcode_set = false;
            if (!empty($data->passcode)) {
              $data->passcode_set = true;
            }
            unset($data->passcode);
            
            if (isset($data->image) && !empty($data->image)) {
              $data->image_reference = (object) array(
                "id" => $data->image,
                "name" => basename($data->image),
                "original" => $data->image,
                "thumbnail" => null,
                "large" => null
              );
              $data->image_reference->original = $data->image;
              if (strpos($data->image, "http://") !== 0 || strpos($data->image, "https://") !== 0) {
                $data->image_reference->original = $this->config["base_url"] . $data->image;
              }
              $data->image_reference->thumbnail = Utilities::get_thumbnail($data->image_reference->original);
              $data->image_reference->large = Utilities::get_large($data->image_reference->original);
            }

          }

          return $data;

        };

        $data = array();
        foreach ($list as $value) {
          $member_data = $this->database->select(
            "user", 
            "*", 
            array("id" => $value->user), 
            null, 
            $this->controls["view"]
          );
          if (!empty($member_data)) {
            array_push($data, $format($member_data, $return_references));
          } else {
            array_push($data, array(
              "id" => $value->user,
              "username" => null,
              "email" => null,
              "name" => null,
              "last_name" => null,
              "nick_name" => null,
              "image" => null,
              "phone" => null,
              "email_verified" => null,
              "phone_verified" => null,
              "ndid_verified" => null,
              "face_verified" => null,
              "create_at" => null,
              "active" => null,
              "user_id" => null,
              "password_set" => null,
              "passcode_set" => null
            ));
          }
        }
        return $this->callback(__METHOD__, func_get_args(), $data);
      } else {
        return array();
      }
    } else {
      return null;
    }
  }

  function add_member($id, $member_user_id, $user_id = 0) {
    if (
      $this->validation->require($id, "ID")
      && $this->validation->require($member_user_id, "User ID")
    ) {
      
      /* initialize: parameters */
      $parameters = array();
      $parameters["id"] = null;
      $parameters["user"] = $member_user_id;
      $parameters["group_id"] = $id;
      $parameters["active"] = true;
      $parameters["user_id"] = (!empty($user_id) ? $user_id : (!empty($this->session) ? $this->session->id : 0));
      $parameters["create_at"] = gmdate("Y-m-d H:i:s");

      $data = $this->database->insert(
        "member", 
        $parameters, 
        $this->controls["create"],
        false
      );
      if (!empty($data)) {
        $group_data = $this->database->select(
          "group", 
          "*", 
          array("id" => $id), 
          null, 
          true
        );
        if (!empty($group_data)) {
          if (!empty($group_data->controls)) {
            $controls = unserialize($group_data->controls);
            foreach ($controls as $control) {
              $member_list = $this->database->select_multiple(
                "member", 
                "*", 
                array("group_id" => $group_data->id), 
                null, 
                null, 
                null, 
                "id", 
                "asc", 
                false,
                false
              );
              foreach ($member_list as $member_data) {
                $control_parameters = array(
                  "id" => null,
                  "user" => $member_data->user,
                  "rules" => $control["rules"],
                  "behaviors" => $control["behaviors"],
                  "create_at" => gmdate("Y-m-d H:i:s"),
                  "active" => true,
                  "module_id" => $control["module_id"],
                  "group_id" => $group_data->id,
                  "user_id" => (!empty($this->session) ? $this->session->id : 0)
                );
                $controls_filters = array(
                  "user" => $member_data->user,
                  "module_id" => $control_parameters["module_id"]
                );
                $controls_extensions = array(
                  array(
                    "conjunction" => "",
                    "key" => "rules",
                    "operator" => "LIKE",
                    "value" => "'%" . $control_parameters["rules"] . "%'"
                  ),
                  array(
                    "conjunction" => "AND",
                    "key" => "behaviors",
                    "operator" => "LIKE",
                    "value" => "'%" . $control_parameters["behaviors"] . "%'"
                  )
                );
                if (empty(
                  $this->database->select_multiple(
                    "control", 
                    "*", 
                    $controls_filters,
                    $controls_extensions,
                    null,
                    null,
                    null,
                    null,
                    null,
                    false
                  )
                )) {
                  if (empty(
                    $this->database->insert(
                      "control", 
                      $control_parameters, 
                      true,
                      false
                    )
                  )) {
                    array_push($errors, $control_parameters);
                  }
                }
              }
            }
          }
        }

        return $this->callback(
          __METHOD__, 
          func_get_args(), 
          $data
        );
        
      } else {
        return $data;
      }
      
    } else {
      return null;
    }
  }

  function remove_member($id, $member_user_id) {
    if (
      $this->validation->require($id, "ID")
      && $this->validation->require($member_user_id, "User ID")
    ) {
      if (
        $data = $this->database->delete(
          "member", 
          array("group_id" => $id, "user" => $member_user_id), 
          null, 
          $this->controls["delete"],
          false
        )
      ) {

        $data = $data[0];

        $this->database->delete(
          "control", 
          array("group_id" => $id), 
          null, 
          true
        );

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
        $parameters["active"] = (isset($parameters["active"]) ? $parameters["active"] : true);
        $parameters["user_id"] = (!empty($user_id) ? $user_id : (!empty($this->session) ? $this->session->id : 0));
        $parameters["create_at"] = gmdate("Y-m-d H:i:s");
      } else {
        unset($parameters["id"]);
        unset($parameters["create_at"]);
      }
      if (!empty($parameters["controls"]) && is_array($parameters["controls"])) {
        $parameters["controls"] = serialize(
          array_map(function($value) {
            $value["rules"] = implode(",", (!empty($value["rules"]) ? $value["rules"] : []));
            $value["behaviors"] = implode(",", (!empty($value["behaviors"]) ? $value["behaviors"] : []));
            return $value;
          }, $parameters["controls"])
        );
      }
    }
    return $parameters;
  }

  function format($data, $return_references = false) {
    if (!empty($data)) {

      if ($data->active === "1") {
        $data->active = true;
      } else if ($data->active === "0" || empty($data->active)) {
        $data->active = false;
      }
      
      if (!empty($data->controls)) {
        $data->controls = unserialize($data->controls);
        $data->controls = array_map(function($value) {
          $value["rules"] = explode(",", $value["rules"]);
          $value["behaviors"] = explode(",", $value["behaviors"]);
          return $value;
        }, $data->controls);
        if ($return_references === true || (is_array($return_references) && in_array("module_id", $return_references))) {
          $data->controls = array_map(function($value) {
            if (!empty(
              $module_data = $this->database->select(
                "module", 
                "*", 
                array("id" => $value["module_id"]), 
                null, 
                true, 
                false
              )
            )) {
              $value["module_id_reference"] = $module_data;
            };
            return $value; 
          }, $data->controls);
        }
      }
      
      if ($return_references === true || (is_array($return_references) && in_array("members", $return_references))) {
        $data->members = array_map(function($value) { 
          if ($value->active === "1") {
            $value->active = true;
          } else if ($value->active === "0" || empty($value->active)) {
            $value->active = false;
          }
          return $value;
        }, $this->database->select_multiple(
          "member", 
          "*", 
          array("group_id" => $data->id), 
          null, 
          null, 
          null, 
          "id", 
          "asc", 
          true,
          false
        ));
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