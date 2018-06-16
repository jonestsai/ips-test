<?php

namespace App\Http\Controllers;

use App\Http\Helpers\InfusionsoftHelper;
use Illuminate\Http\Request;
use Response;
use App\Module;
use App\User;
use App\Tag;

class ApiController extends Controller
{
    // Todo: Module reminder assigner

    private function exampleCustomer(){

        $infusionsoft = new InfusionsoftHelper();

        $uniqid = uniqid();

        $infusionsoft->createContact([
            'Email' => $uniqid.'@test.com',
            "_Products" => 'ipa,iea'
        ]);

        $user = User::create([
            'name' => 'Test ' . $uniqid,
            'email' => $uniqid.'@test.com',
            'password' => bcrypt($uniqid)
        ]);

        // attach IPA M1-3 & M5
        $user->completed_modules()->attach(Module::where('course_key', 'ipa')->limit(3)->get());
        $user->completed_modules()->attach(Module::where('name', 'IPA Module 5')->first());


        return $user;
    }

    public function moduleReminderAssigner(Request $request){

        $infusionsoft = new InfusionsoftHelper();

        // Get POST "contact_email" parameter
        $contact_email = $request->input('contact_email');

        $contact = $infusionsoft->getContact($contact_email);
        $contact_id = $contact['Id'];
        $contact_products = $contact['_Products'];

        $tag_id = $this->getTagId($contact_email, $contact_products);

        return Response::json([
            "success" => $infusionsoft->addTag($contact_id, $tag_id),
            "message" => "contact:" . $contact_id . " tag:" . $tag_id
        ]);
    }

    /**
     * Return the tag id for the module reminder
     * 
     * @param string, object
     * @return int
     */
    private function getTagId($email, $products){

        $last_modules = array('IPA Module 7', 'IEA Module 7', 'IAA Module 7');

        $user = User::where('email', '=', $email)->first();

        // Get the last completed module based on course purchase order
        $last_completed_module = $this->getLastCompleted($user, $products);

        if (empty($last_completed_module)){ // User has not started
            $next_module_name = $this->getFirstModuleName($products);
        } elseif ($this->completedAllModules($last_completed_module, $products)) { // User has completed all last modules
            $next_module_name = 'completed';
        } else {
            if (in_array($last_completed_module->name, $last_modules)){ // Check if last completed module is the last module in course
                $next_module_name = $this->getNextCourseModuleName($last_completed_module, $products);
            } else { // Not the last module in course
                $next_module_name = $this->getNextModuleName($last_completed_module);
            }
        }
        
        $tag_id = $this->getTagIdFromName($next_module_name);

        return $tag_id;
    }

    /**
     * Return the last completed module of the last course in progress
     * 
     * @param object, string
     * @return object
     */
    private function getLastCompleted($user, $products){

        // Go through the courses customer bought from last to first
        $products = array_reverse(explode(',', $products));
        foreach ($products as $product){
            $last_completed_module = $user->completed_modules()->where('course_key', '=', $product)->orderBy('id', 'desc')->first();
            if (!empty($last_completed_module)){
                break;
            }
        }
        return $last_completed_module;
    }

    /**
     * Get the first module name
     * 
     * @param string
     * @return int
     */
    private function getFirstModuleName($products){

        $products = explode(',', $products);
        $next_module_name = strtoupper($products[0]) . ' Module 1';
        return $next_module_name;
    }

    /**
     * Retrun true if user has completed all last modules of purchased courses
     * Otherwise, return false
     * 
     * @param object, string
     * @return boolean
     */
    private function completedAllModules($last_user_module, $products){

        $products = explode(',', $products);
        $last_course_module_name = strtoupper(end($products)) . ' Module 7';
        
        if ($last_user_module->name === $last_course_module_name){
            $boolean = true;
        } else {
            $boolean = false;
        }

        return $boolean;
    }

    /**
     * Return the first module name in the next course
     * 
     * @param object
     * @return string
     */
    private function getNextCourseModuleName($last_user_module, $products){
        
        $products = explode(',', $products);
        foreach ($products as $index => $product){
            if ($product === $last_user_module->course_key){
                $next_index = $index + 1;
                try {
                    $next_course = $products[$next_index];
                    break;
                }
                catch (\Exception $e) {
                    return $e->getMessage();
                }
            }
        }

        $next_module_name = strtoupper($next_course) . ' Module 1';

        return $next_module_name;
    }

    /**
     * Return the next module name in the same course
     * 
     * @param object
     * @return string
     */
    private function getNextModuleName($last_user_module){

        $name_array = explode(' ', $last_user_module->name);
        $name_array[2] = (int)$name_array[2] + 1;
        $next_module_name = implode(' ', $name_array);
        
        return $next_module_name;
    }

    /**
     * Return the tag id from module name
     * 
     * @param string
     * @return int
     */
    private function getTagIdFromName($module_name){

        if ($module_name === 'completed'){
            $module_reminder_name = 'Module reminders completed';
        } else {
            $module_reminder_name = 'Start ' . $module_name . ' Reminders';
        }
        
        $tag = Tag::where('name', '=', $module_reminder_name)->firstOrFail();
        
        return $tag['id'];
    }
}
