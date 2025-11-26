class FileciteSanitizer:
    """
    Remove any content between:
        U+E200 (start) and U+E201 (end)

    Works correctly in streaming, even if
    the sequence is split across multiple chunks.
    """

    START_MARK = "\uE200"  # U+E200 character (start of quote sequence)
    END_MARK = "\uE201"    # U+E201 character (end of quote sequence)

    def __init__(self, safety_margin: int = 128):
        self.buffer = ""
        self.safety_margin = safety_margin
        self._inside_hidden = False  # Checking if we are inside a hidden block (the quote sequence)

    def _process_text(self, text: str) -> str:
        """
        Process a chunk of text and remove everything between
        START_MARK and END_MARK, taking into account the current state
        (self._inside_hidden).
        """
        if not text:
            return ""

        out_chars = []

        for ch in text:
            if self._inside_hidden:
                # The end of the hidden block
                if ch == self.END_MARK:
                    self._inside_hidden = False

                continue
            else:
                # Outside a hidden block and checking for start
                if ch == self.START_MARK:
                    self._inside_hidden = True
                    continue
                else:
                    out_chars.append(ch)

        return "".join(out_chars)

    def sanitize(self, chunk: str) -> str:
        """
        Cleaning for streaming.
        Any text between START_MARK and STOP_MARK will be removed.
        """
        if not chunk:
            return ""

        self.buffer += chunk

        # If the buffer is smaller than or equal to the safety margin, we cannot emit anything yet
        if len(self.buffer) <= self.safety_margin:
            return ""

        # Emit everything except the last `safety_margin` characters
        emit_upto = len(self.buffer) - self.safety_margin
        to_process = self.buffer[:emit_upto]
        self.buffer = self.buffer[emit_upto:]

        # Process the chunk to emit and return cleaned text
        return self._process_text(to_process)

    def flush(self) -> str:
        """
        Clean up any remaining text in the buffer at the end of the stream.
        """
        if not self.buffer:
            return ""

        to_process = self.buffer
        self.buffer = ""
        return self._process_text(to_process)

    @classmethod
    def remove_all(cls, text: str) -> str:
        """
        Clean an entire text block by removing all content
        between START_MARK and END_MARK.
        """
        if not text:
            return ""

        inside_hidden = False
        out_chars = []

        for ch in text:
            if inside_hidden:
                if ch == cls.END_MARK:
                    inside_hidden = False
                continue
            else:
                if ch == cls.START_MARK:
                    inside_hidden = True
                    continue
                out_chars.append(ch)

        return "".join(out_chars)
