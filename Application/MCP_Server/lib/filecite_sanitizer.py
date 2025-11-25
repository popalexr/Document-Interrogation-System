import re


class FileciteSanitizer:
    """
    Elimină secvențele de tip:

    \uE200filecite\uE202turnXfileY...\uE201
    """

    # prinde TOT block-ul: start (U+E200), "filecite", separator (U+E202), orice, terminator (U+E201)
    _pattern = re.compile(r"\uE200filecite\uE202.*?\uE201", re.DOTALL)

    def __init__(self, safety_margin: int = 128):
        self.buffer = ""
        self.safety_margin = safety_margin

    def sanitize(self, chunk: str) -> str:
        """
        Curăță pentru streaming.
        """
        if not chunk:
            return ""

        self.buffer += chunk

        if len(self.buffer) <= self.safety_margin:
            return ""

        safe_part = self.buffer[:-self.safety_margin]
        self.buffer = self.buffer[-self.safety_margin:]

        return self._pattern.sub("", safe_part)

    def flush(self) -> str:
        """
        Curăță restul la final.
        """
        if not self.buffer:
            return ""

        cleaned = self._pattern.sub("", self.buffer)
        self.buffer = ""
        return cleaned

    @classmethod
    def remove_all(cls, text: str) -> str:
        """
        Curăță un text complet.
        """
        if not text:
            return ""
        return cls._pattern.sub("", text)
