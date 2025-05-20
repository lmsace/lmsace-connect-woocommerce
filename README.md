
LMSACE Connect
==================

**LMSACE Connect** is a woocommerce integration plugin that focused on the Moodle™ Software + WooCommerce Integrations.

It gives the course creatores as options to import courses from the Moodle™ Software and create courses as woocommerce products and sell their courses to customer.

Once the course products are purchased by customer, Plugin will create those users on Moodle™ Software and enrol them into the linked course.

With the new version, we've added exciting features to further enhance your e-learning experience. Enjoy the seamless login with SSO, sync multiple courses with a single product, and import course outline data effortlessly.


### Moodle™ Software Side Plugin.

Please install the Moodle™ Software side plugin to generate the token easier and setup the connection between Moodle™ Software and Wordpress. This plugin should be installed on your Moodle™ Software.

[https://github.com/lmsace/lmsace-connect-moodle](https://github.com/lmsace/lmsace-connect-moodle)


### Dependencies

Component: WooCommerce

Version: 3+

Plugin URI: https://woocommerce.com/


## Documenation

The official documentation to setup connections and SSO between Moodle™ Software and WooCommerce using LMSACE Connect can be found at [https://github.com/lmsace/lmsace-connect-woocommerce/wiki](https://github.com/lmsace/lmsace-connect-woocommerce/wiki)


## Bugs and Problems Report.

This plugin is carefully developed and highly tested with multiple versions of Moodle™ Software, WordPress, and WooCommerce. If you found any issues with LMSACE Connect or you have any problems or difficulty with its workflow please feel free to report on Github: http://github.com/lmsace/lmsace-connect/issues. We will do our best to solve the problems.


## Feature Proposals.

You are developing a custom feature for LMSACE Connect. Please create a pull request and create an issue on Github: https://github.com/lmsace/lmsace-connect/issues.


## Features.

1. Selective Course Imports.
2. Link the course with existing products.
3. User creation and enrolment on order completion.
3. User un-enrollment on order cancellation.
4. Selectable role for participants.
5. Seamless login with SSO
6. Bundle multiple courses in a product.
7. Import courses with syllabus summary.



### Installation steps using ZIP file.

> **Note:** Make sure the **Woocommerce** is correctly installed.

1. Download the latest "**LMSACE Connect WooCommerce**" plugin file from the [github releases](https://github.com/lmsace/lmsace-connect-woocommerce/releases)

2. Next login as Site Administrator

3. Go to '*Plugins*' -> '*Add New*' -> '*Upload Plugin*', On here upload the plugin zip '**lmsace-connect.zip**'.

4. Click the "**Instal Now**" button.


> You will get a **success message** once the plugin is installed successfully.


5. By clicking the "**Activate**" button on the success page, Plugin will be activated and you will redirect to Your dashboard.


### Installation steps using Git.


1. Clone [**LMSACE Connect WooCommerce**](https://github.com/lmsace/lmsace-connect-woocommerce) plugin Git repository into the folder '*plugins*'.

> '*Your root diroctory*' -> '*wp-content*' -> '*plugins*'.

2. Rename the folder name to '**lmsace-connect**'.

3. Next login as Site Administrator

4. Go to the '*Plugins*' -> '*Installed plugins*'. Here LMSACE Connect plugin will be listed.

5. Activate the plugin by clicking the "**Activate**" button placed on the bottom of the plugin name, Plugin will get the success message.


> Once the wordpress side plugin was installed, Please **install the mandatory Moodle™ software side plugin to generate the token and setup the connection** between Moodle and Wordpress.


## Copyright

@LMSACE DEV TEAM [https://lmsace.com](https://lmsace.com)


### Review

You feel LMSACE Connect helps you to sell courses easier. "**Give a review and rating on wordpress.org**". We are looking for your comments.


### Changes log

1. Version 1.0 ( Released on 28 March 2022 )

     - Selective Course Imports.
     - Link course with existing products.
     - User creation and enrolment on order completion.
     - User un-erollment on order cancellation.
     - Selectable role for participants.


2. Version 2.0 ( Release on 25 July 2023 )

     - Single Sign ON
     - Bundle courses in a product.
     - Import course data.
