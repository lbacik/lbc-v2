
class LineTools {
  static pointOnLine(A, B, H) {
    return (A <= H && H <= B) || (A >= H && H >= B)
  }

  static pointOnOppositeEnd(line) {
    return line[0].slice()
  }

  static getDimensionIndex(line) {
    if (line[0][0] === line[1][0]) {
      return 1
    }
    return 0
  }
}

// module.exports = LineTools

export default LineTools
