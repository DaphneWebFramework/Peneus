# TemplateLibrary

[![Backend Tests](https://github.com/DaphneWebFramework/TemplateLibraryTest/actions/workflows/test-backend.yml/badge.svg)](https://github.com/DaphneWebFramework/TemplateLibraryTest/actions/workflows/test-backend.yml)

![](assets/masthead.png)

> <sub><sup>Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere ligula.</sup></sub>

## Overview

Duis vulputate vitae erat vel efficitur. Cras sed enim a erat tincidunt ornare sed a eros. Suspendisse vel venenatis nulla. Vivamus aliquam congue elit laoreet.

## Configuration

> You can remove this section after completing the instructions.

When starting a new library based on this template, you must add a **Secret** token in your library repository so that the automated documentation workflow included in this template (`generate-documentation.yml`) can push updates to the central [**Documentation**](https://github.com/DaphneWebFramework/Documentation) repo. Follow the steps below:

- Navigate to **Settings** for the newly derived repository.
- Click **Secrets and variables** > **Actions**.
- Click **New repository secret**.
- For **Name**, enter `PERSONAL_ACCESS_TOKEN`.
- For **Secret**, paste the required token.
- Click **Add secret**.

## Installation

### Adding

```
git submodule add https://github.com/DaphneWebFramework/TemplateLibrary.git source/backend/TemplateLibrary
```

### Updating

```
git submodule update --remote --merge source/backend/TemplateLibrary
```

## See Also

- [Tests](https://github.com/DaphneWebFramework/TemplateLibraryTest)
- [Documentation](https://github.com/DaphneWebFramework/Documentation/blob/main/TemplateLibrary/README.md)

## License

This project is distributed under the Creative Commons Attribution 4.0 International License. For more information, visit [CC BY 4.0 License](https://creativecommons.org/licenses/by/4.0/).
