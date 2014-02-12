## Installing
To install, simply add the library to your project. For any page or function you want to use the library in, include the file **includes/classes.php** to enable auto-loading of classes.

Before you use any commands,  you need to create a **amazon-config.php** file with your account credentials. Start by copying the template provided (*amazon-config.default.php*) and renaming the file.

Only the first section of the config file needs to be filled out. If you are operating outside of the United States, be sure to change the Amazon Service URL as well. Everything after that point is pertaining to Amazon's API. Be sure to keep these values up to date, as Amazon could change them in a new API version release.

You can also link the built-in logging system to your own logging system by putting the function name in the *$logfunction* parameter.

In the event that PHP does not have the correct permissions to create a file in the library's main directory, you will have to create the log file as "log.txt" and give all users permission to edit it.

## Example Usage
The general work flow for using one of the objects is this:
1. Create an object for the task you need to perform.
2. Load it up with parameters, depending on the object, using *set____* methods.
3. Submit the request to Amazon. The methods to do this are usually named *fetch____* or *submit____* and have no parameters.
4. Reference the returned data, whether as single values or in bulk, using *get____* methods.
5. Monitor the performance of the library using built-in logging system.

Note that if you want to act on more than one Amazon store, you will need a separate object for each store.
