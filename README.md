
LMSACE Connect
==================

**LMSACE Connect** is a woocommerce integration plugin that focused on the Moodle + WooCommerce Integrations.

It gives the course creatores as options to import courses from the Moodle LMS and create courses as woocommerce products and sell their courses to customer.

Once the course products are purchased by customer, Plugin will create those users on Moodle LMS and enrol them into the linked course.


### Version

Plugin version: 1.0

Released on: 28 March 2022

Authors: https://lmsace.com, LMSACE Dev Team

### Plugin Repository

Publlic Git Repository
[https://github.com/lmsace/lmsace-connect](https://github.com/lmsace/lmsace-connect)

#### Moodle Side Plugin.
Helps to genearate the token easier.
[https://github.com/lmsace/lmsace-connect-moodle](https://github.com/lmsace/lmsace-connect-moodle)

## Dependencies

Component: WooCommerce

Version: 3+

Plugin URI: https://woocommerce.com/

## Documenation

The official documentation for LMSACE Connect can be found at [https://lmsace.com/docs/lmsace-connect](https://lmsace.com/docs/lmsace-connect).

## Bugs and Problems Report.

This plugin is carefully developed and highly tested with multiple version of Moodle LMS, WordPress, and WooCommerce. If you found any issues with LMSACE Connect or you have any problems or difficulty with its workflow please feel free to report on Github: http://github.com/lmsace/lmsace-connect/issues. We will do our best to solve the problems.

## Feature Proposals.

You are develop custom feature for LMSACE Connect. Please create pull request and create issue on Github: https://github.com/lmsace/lmsace-connect/issues.


## Features.

1. Selective Course Imports.
2. Link course with existing products.
3. User creation and enrolment on order completion.
3. User unerollment on order cancellation.
4. Selectable role for pariticipants.

## Steps to setup connection.

> Please install the moodle side plugin and generate webservice token in your Moodle LMS.<br> Follow the steps mentioned on the readme to generate token. [https://github.com/lmsace/lmsace-connect-moodle](https://github.com/lmsace/lmsace-connect-moodle)

1. Log in as site administrator and go to admin backend.

2. Go to the **LMSACE Connect**.

3. Enter your Moodle Site URL and Site token which you can copy from your Moodle **LMSACE Connect** "*General*" tab.

> Note: Test the given details are working fine using the "**Test Connection**" button.

4. Please click the '**Save Change**' button to save the details.

![Connection Setup](https://www.lmsace.com/docs/lmsace-connect/images/connection-setup.png)

> After you have saved the details, you can get the notification about the connection status. If you received any errors then please check the given details.

5. Once you completed the connection setup successfully. Please goto the "**General Setup**" tab.

6. In General setup, Please select the role for a customer user. The selected role will be assigned for the pariticipants who enrolled in the course from your WooCommerce.

![Connection Setup](https://www.lmsace.com/docs/lmsace-connect/images/general-setup.png)

## Import courses as products.


> **LMSACE Connect** brings the course import as much easier compare with other moodle + woocommerce integrations options.

1. Goto the "**Import courses**" tab in "**LMSACE Connect**" admin menu.

2. In Import page, you can view the list of courses available in the connected Moodle LMS instance.

3. Click the checkbox to select the courses you want to import.

4. Select any or all options based on your need. After the course table, You can found the various "***Import Options***".

5. Click the "**Start Import Courses**" button to import the selected courses.

> **LMSACE Connect** will import the courses in background using WP_Schedule if you have tried to import more than 25 courses in single time.

![Import Courses](https://www.lmsace.com/docs/lmsace-connect/images/import-course.png)


### Installation steps using ZIP file.

> **Note:** Make sure the **Woocommerce** is correctly installed.

1. Clone Project Git repository in any of your prefered location.

2. Rename the folder name into '**lmsace-connect**'.

3. Next login as Site administrator

4. Go to '*Plugins*' -> '*Add New*' -> '*Upload Plugin*', On here upload the plugin zip '**lmsace-connect.zip**'.

5. Click the "**Instal Now**" button.


> You will get **success message** once the plugin installed successfully.


6. By clicking "**Activiate**" button on success page , Plugin will activated and you will redirect to Your dashboard.


### Installation steps using Git.


1. Clone **LMSACE Connect** plugin Git repository into the folder '*plugins*'.

> '*Your root diroctory*' -> '*wp-content*' -> '*plugins*'.

2. Rename the folder name into '**lmsace-connect**'.

3. Next login as Site administrator

4. Go to the '*Plugins*' -> '*Installed plugins*'. On here LMSACE Connect plugin will listed.

5. Activate the plugin by clicking "**Activiate**" button placed on the bottom of the plugin name, Plugin will get the success message.


## Copyright

LMSACE DEV TEAM https://lmsace.com

### Review

You feel LMSACE Connect helps you to sell courses easier. Give a review and rating on wordpress.org. We are looking for your comments.