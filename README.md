# Networkteam.Neos.FrontendLogin

Neos package for frontend login inspired
by  [Flowpack.Neos.FrontendLogin](https://github.com/Flowpack/Flowpack.Neos.FrontendLogin).
It provides a mixin for MemberAreaRootPages. The package makes use of the `accessRoles` property of `NodeInterface`.

**Note:** It seems that the current Neos versions `neos/neos:7.*` and `neos/neos:8.*` are compatible with this package
as the deprecated parts are not yet removed. But be aware of breaking changes in the near future.

## Features

* Place a MemberAreaRootPage within your page tree and all pages beneath it including the member area page itself will
  be protected
* Configure redirect page after login and logout
* Redirect to configured login form when a protected page is requested without valid login.
  After successful login you will be redirected to initially requested page (referer).
* Multiple member areas with different access roles are possible
* Optional second factor (e.g. a one-time code sent by email) with a pluggable backend

## Installation

Install the package via composer

```bash
composer require networkteam/neos-frontendlogin
```

Campatibility with `neos/neos` Package

| Neos.Neos | Networkteam.Neos.FrontendLogin |
|-----------|--------------------------------|
| `4.*`     | `1.3.*`                        |
| `5.*`     | `1.4.*`                        |
| `7.*`     | `1.4.*`                        |
| `8.*`     | `1.5.*`                        |

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
`Networkteam.Neos.FrontendLogin:MemberArea` as parent role. Otherwise, you won't be able to select the role within
MemberAreaRootPage node.

When you set access roles on your MemberAreaRootPage via the inspector and apply the changes, these access roles will be
set on all
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
    'Neos.Neos:Document': true
    'Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot': true
  ui:
    label: 'Member area'
```

### Define fusion object

For your defined nodeType you need a suitable fusion object. An example configuration could look as follows:

*Packages/Application/Your.Package/Resources/Private/Fusion/MemberAreaRootPage.fusion*

```fusion
# MemberAreaRootPage
prototype(Your.Package:MemberAreaRootPage) < prototype(Neos.Neos:Page) {
  
}
```

**Hint:** Usually you would inherit from the default page prototype of your site package.

### Add pages and login form

Now you can log into Neos backend and create a new **MemberAreaRootPage**. Define which users should have access to this
member area by selecting access roles and apply the changes.

Next you need to add a **login form** on a page which is not protected. Do not place the login form within
your member area or the MemberAreaRootPage. Otherwise, your users won't be able to access the login form.

Now go back to the previously created MemberAreaRootPage and select the page containing the login form (
`Login form page`).

Additionally, you can add further pages beneath your MemberAreaRootPage. They will be protected.

## Adding your own MemberArea roles

If you define your own MemberArea roles via `Policy.yaml`, make sure that you add them as `parentRoles` to
`Neos.Neos:AbstractEditor` role definition. Otherwise pages only having your new roles will not be visible in Neos
backend.
This could also lead to error during publishing.

**Policy.yaml**

```yaml
roles:
  'Your.Package:UserWithFrontendAccess':
    parentRoles: [ 'Networkteam.Neos.FrontendLogin:MemberArea' ]

  'Neos.Neos:AbstractEditor':
    parentRoles: [ 'Your.Package:UserWithFrontendAccess' ]
```

## Second factor

After the password check the package can ask for a second factor, e.g. a one-time code sent by email. Until the
code is verified, every request of the authenticated frontend user is redirected to a code form at
`/frontend-login/2fa`. Only the code form, "send new code" and logout are reachable.

### Backend

The package does not generate, send or store codes. Bind your own implementation of
`Networkteam\Neos\FrontendLogin\Security\SecondFactor\SecondFactorProviderInterface` in `Objects.yaml`:

```yaml
Networkteam\Neos\FrontendLogin\Security\SecondFactor\SecondFactorProviderInterface:
  className: Your\Package\Security\EmailCodeSecondFactorProvider
```

* `isRequired(Account)`: whether the account has to pass a second factor
* `sendCode(Account)`: deliver a new code (called once when the challenge starts and on "send new code")
* `verifyCode(Account, string)`: check the entered code. Expiry, attempt limits and throttling belong here.
* `getRememberRevocationMarker(Account)`: a value that invalidates all remembered browsers of the account when it
  changes (e.g. a "browser trust reset at" timestamp), see below

The default implementation never requires a second factor, so the login behaves as without this feature.

### Code page layout

The code form is rendered with the site's Fusion. The context node is the closest node with the mixin
`Networkteam.Neos.FrontendLogin:Mixins.PortalRoot` above the member area root of the account (see
`roleToMemberAreaMapping`), otherwise the site node. Mark the root pages of your portals with the mixin and override the
page prototype to use your layout:

```neos
prototype(Networkteam.Neos.FrontendLogin:SecondFactorPage) < prototype(Your.Site:Document.DefaultPage) {
    body.content = Networkteam.Neos.FrontendLogin:Components.Atoms.SecondFactorForm
}
```

The page must not be cached, because it contains the CSRF token and flash messages of the session. The package sets
`@cache.mode = 'uncached'` on the prototype, keep it when overriding. Use `SecondFactorFormProps` to build your own
form markup. The page is loaded in the default content dimensions.

### Remember browser

Users may skip the code in a browser for a configured time after ticking "remember this browser". The feature is off by
default:

```yaml
Networkteam:
  Neos:
    FrontendLogin:
      secondFactor:
        rememberBrowser:
          duration: 'P30D'
```

The browser gets a signed, `HttpOnly`, `Secure`, `SameSite=Lax` cookie per account. It is bound to a fingerprint of the
account's credentials source and the revocation marker of the backend, so a password change or a changed marker
requires the code again at the next login. Logout keeps the cookie.

## Password reset

To give your frontend users the possibility to reset there password you can install and use the
package [Networkteam.Neos.PasswordReset](https://github.com/networkteam/Networkteam.Neos.PasswordReset).

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