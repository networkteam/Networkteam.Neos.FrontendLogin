# Networkteam.Neos.FrontendLogin

Neos package for frontend login inspired by  [Flowpack.Neos.FrontendLogin](https://github.com/Flowpack/Flowpack.Neos.FrontendLogin).
It provides a mixin for MemberAreaRootPages. The package makes use of the `accessRoles` property of `NodeInterface`.

## Features

* Place a MemberAreaRootPage within your page tree and all pages beneath it including the member area page itself will be protected
* Configure redirect page after login and logout 
* Redirect to configured login form when a protected page is requested without valid login.
  After successful login you will be redirected to initially requested page (referer).
* Multiple member areas with different access roles are possible

## Installation

Install the package via composer

```bash
composer require networkteam/neos-frontendlogin
```

Campatibility with `neos/neos` Package

| Neos.Neos | Networkteam.Neos.FrontendLogin |
|-----------|--------------------------------|
| `4.x`     | `1.x`                          |
| `5.x`     | `main` branch                  |

## Migration

This package provides a node migration for copying existing internal accessRoles values to a regular node property.

```bash
./flow node:migrate 20210419111000 --confirmation true
```

## NodeTypes

**Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot**

The mixin `Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot` defines the root point of a specific
member area and is of type `Neos.NodeTypes:Page`

## Authentication via roles

This packages makes use of the neos flow security framework. For further details you can have a look into the
[documentation of the flow framework](https://flowframework.readthedocs.io/en/stable/TheDefinitiveGuide/PartIII/Security.html?highlight=roles#defining-privileges-policies).

Two role definitions are provided:
* **Networkteam.Neos.FrontendLogin:MemberArea** (abstract): Interface for MemberArea roles. It is used within 
  access role selection of MemberAreaRootPages.
* **Networkteam.Neos.FrontendLogin:FrontendUser**: Concrete access role implementation

You can define your own frontend user roles by adding them to the `Policy.yaml` of your package. Make sure that you add 
`Networkteam.Neos.FrontendLogin:MemberArea` as parent role. Otherwise you won't be available to select the role within 
MemberAreaRootPage node.

When you set access roles on your MemberAreaRootPage via the inspector and apply the changes, these access roles will be set on all 
DocumentNodes beneath that MemberAreaRootPage as well. This ensures that all these pages can only be access by users 
having one of the selected roles.

## Create Frontend Users

To create a new Frontend User you can use the `neos.neos:user:create` command, e.g.
 
```bash
./flow user:create --authentication-provider "Networkteam.Neos.FrontendLogin:Frontend" --roles "Networkteam.Neos.FrontendLogin:FrontendUser"
```

or use the user management module inside Neos backend.


## Create member area

The package does not supply a concrete implementation. It does only supply a mixin.
To create a member area you need to define a specific nodeType for MemberAreaRootPages which 
implements the mixin provided by this package.

### Define your nodeTypes

You need to define one nodeType for MemberAreaRootPages.

An example configuration could look as follows:

*Packages/Application/Your.Package/Configuration/NodeTypes.MemberAreaRootPage.yaml*
```yaml
'Your.Package:MemberAreaRootPage':
  superTypes:
    'Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot': true
  ui:
    label: 'Member area'
```

### Define fusion object

For your defined nodeType you need a suitable fusion object. An example configuration could look as follows:

*Packages/Application/Your.Package/Resources/Private/Fusion/MemberAreaRootPage.fusion*
```fusion
# MemberAreaRootPage
prototype(Your.Package:MemberAreaRootPage) < prototype(Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot) {

}
```

### Add pages and login form

Now you can log into Neos backend and create a new **MemberAreaRootPage**. Define which users should have access to this 
member area by selecting access roles and apply the changes.

Next you need to add a **login form** on a page which is not protected. Do not place the login form within 
your member area or the MemberAreaRootPage. Otherwise, your users won't be able to access the login form.

Now go back to the previously create MemberAreaRootPage and select the page containing the login form (`Login form page`).

Additionally you can add further pages beneath your MemberAreaRootPage. They will be protected.

## Adding your own MemberArea roles

If you define your own MemberArea roles via `Policy.yaml`, make sure that you add them as `parentRoles` to
`Neos.Neos:AbstractEditor` role definition. Otherwise pages only having your new roles will not be visible in Neos backend.
This could also lead to error during publishing. 

**Policy.yaml**

```yaml
roles:
  'Your.Package:UserWithFrontendAccess':
    parentRoles: ['Networkteam.Neos.FrontendLogin:MemberArea']

  'Neos.Neos:AbstractEditor':
    parentRoles: ['Your.Package:UserWithFrontendAccess']
```

## Password reset

To give your frontend users the possibility to reset there password you can install and use the package [Networkteam.Neos.PasswordReset](https://github.com/networkteam/Networkteam.Neos.PasswordReset).

## Translation

To translate the login error message or login form labels create a xliff file for each language in your package and 
set properties `original="Main"` and `product-name="Networkteam.Neos.FrontendLogin"` on `<file>` tag. Now you can
override the language keys from the original translation file of `Networkteam.Neos.FrontendLogin` package you like.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
    <file original="Main" product-name="Networkteam.Neos.FrontendLogin" source-language="en" datatype="plaintext">
        <body>
            <trans-unit id="components.atoms.loginform.username" xml:space="preserve">
                <source>Enter your username</source>
            </trans-unit>
            <trans-unit id="components.atoms.loginform.password" xml:space="preserve">
                <source>Enter your password</source>
            </trans-unit>

            <trans-unit id="authentication.onAuthenticationFailure.authenticationFailed" xml:space="preserve">
                <source><![CDATA[<strong>Authentication failed!</strong><br />The login could not be performed with the given credentials.]]></source>
            </trans-unit>
        </body>
    </file>
</xliff>

```