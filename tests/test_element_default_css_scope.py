"""Guard Breakdance defaults against cascading onto native Section/Div elements."""

import re
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
ELEMENTS = {
    "Wrapper_Div": ("bde-div", "iceberg-wrapper-div"),
    "Inner_Section": ("bde-section", "iceberg-inner-section"),
}


class ElementDefaultCssScopeTest(unittest.TestCase):
    def test_default_css_only_matches_own_element(self):
        for directory, (base_class, own_class) in ELEMENTS.items():
            with self.subTest(element=directory):
                source = (ROOT / "elements" / directory / "element.php").read_text()
                css = (ROOT / "elements" / directory / "default.css").read_text()
                self.assertTrue(
                    re.search(rf"function className\(\).*?return ['\"]{base_class}['\"]", source, re.S),
                    f"{directory} must retain its legacy Breakdance class",
                )
                self.assertTrue(
                    re.search(rf"function additionalClasses\(\).*?['\"]name['\"]\s*=>\s*['\"]{own_class}['\"].*?['\"]template['\"]\s*=>\s*['\"]yes['\"]", source, re.S),
                    f"{directory} needs an unconditional custom class",
                )
                for selector in re.findall(r"([^{}]+)\{[^{}]*\}", css):
                    # Every default rule must require the custom root class;
                    # plain .bde-section or .bde-div matches native Breakdance elements.
                    self.assertIn(
                        f".{base_class}.{own_class}", selector,
                        f"Native elements are matched by {directory}: {selector.strip()}",
                    )

    def test_custom_inner_section_does_not_style_nested_sections(self):
        css = (ROOT / "elements" / "Inner_Section" / "default.css").read_text()
        for selector in re.findall(r"([^{}]+)\{[^{}]*\}", css):
            if ".section-container" in selector:
                self.assertIn(
                    "> .section-container", selector,
                    f"Nested native sections are matched by: {selector.strip()}",
                )


if __name__ == "__main__":
    unittest.main()
