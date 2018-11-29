# Networkteam.Neos.FrontendLogin

Neos package for frontend login inspired by  [Flowpack.Neos.FrontendLogin](https://github.com/Flowpack/Flowpack.Neos.FrontendLogin).
It provides a mixin for member area root pages. The packages makes use of the `accessRoles` property of `NodeInterface`.

## Features

* Place a member area page within your page tree and all pages beneath it including the member area page itself will be protected
* Redirect to configured login form when a protected uri is requested
* Configure redirect page after login and logout or redirect to requested protected page (referer) after login

## Installation

Install the package via composer

```bash
composer require networkteam/neos-frontendlogin
```

## NodeTypes

**Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot**

The mixin `Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot` defines the root point of a specific
member area.

## Create member area

The packages does not supply a concrete implementation. It does only supply a mixin.
To create a member area you need to define a specific nodeType for member area root pages which 
implement the mixin provided by this package.

### Define specific nodeTypes

You need to define one nodeType for *member area root pages*.

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
# Member Area Root page
prototype(Your.Package:MemberAreaRootPage) < prototype(Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot) {

}
```

### Add pages and login form

Now you can log into Neos backend and create a new member area root page.
Next you need to add a login form on a page which is **not of type** `Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot`.
Additionally you can add further pages beneath your member area root page. They will be protected.

## Create Frontend Users

To create a new Frontend User you can use the *neos.neos:user:create* command, e.g.
 
```bash
./flow user:create --authentication-provider "Networkteam.Neos.FrontendLogin:Frontend" --roles "Networkteam.Neos.FrontendLogin:FrontendUser"
```

or use the user management module inside Neos backend.